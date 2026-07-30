<?php
declare(strict_types=1);

namespace NexusAI\Workforce\AI\Workflows;

use NexusAI\Workforce\AI\Agents\Orchestrator;
use NexusAI\Workforce\Repositories\EmployeeRepository;
use NexusAI\Workforce\AI\Factories\ModelFactory;

/**
 * Executes multi-agent sequential workflows with persistent state.
 */
class ExecutionEngine {

	private $employee_repository;

	public function __construct() {
		$this->employee_repository = new EmployeeRepository();
	}

	/**
	 * Run a defined workflow.
	 *
	 * @param array $workflow_definition JSON-decoded definition (steps, agents).
	 * @param string $input              The initial trigger input.
	 * @return array                     The final state and results of each step.
	 */
	public function run( array $workflow_definition, string $input ): array {
		$state = [ 'initial_trigger' => $input ];
		$results = [];
		$total_prompt_tokens = 0;
		$total_completion_tokens = 0;
		$total_cost = 0.00;
		$total_start_time = microtime( true );

		( new \NexusAI\Workforce\Utils\AuditLogger() )->log( 'workflow_started', "Started workflow execution with trigger: " . substr( $input, 0, 100 ) );

		$steps = isset( $workflow_definition['steps'] ) ? $workflow_definition['steps'] : [];
		if ( ! is_array( $steps ) ) {
			$steps = [];
		}

		$cost_calc = new \NexusAI\Workforce\Utils\CostCalculator();
		$usage_repo = new \NexusAI\Workforce\Repositories\UsageLogRepository();

		foreach ( $steps as $index => $step ) {
			$agent_id = (int) $step['agent_id'];
			$task     = $step['task_description'];

			$agent = $this->employee_repository->get_by_id( $agent_id );
			if ( ! $agent ) {
				$results[] = [ 'step' => "Step $index", 'error' => "Agent $agent_id not found." ];
				continue;
			}

			$orchestrator = $this->get_orchestrator( $agent );

			// Build comprehensive context from all previous steps
			$context = "WORKFLOW STATE:\n";
			foreach ( $state as $key => $val ) {
				if ( is_array( $val ) && isset( $val['content'] ) ) {
					$context .= "[$key]: " . $val['content'] . "\n";
				} else {
					$context .= "[$key]: " . ( is_string($val) ? $val : json_encode($val) ) . "\n";
				}
			}

			$prompt = "You are participating in a multi-agent workflow.
			Current Task: $task

			$context

			Produce your output based on your specific role and the current workflow state.";

			$step_start_time = microtime( true );
			$output = $orchestrator->process_request( $prompt, $agent );
			$elapsed_seconds = round( microtime( true ) - $step_start_time, 2 );

			// Parse metrics and log usage
			$prompt_tokens = $output['usage']['prompt_tokens'] ?? 0;
			$comp_tokens   = $output['usage']['completion_tokens'] ?? 0;
			$model         = $agent['model'] ?? 'gpt-4o';
			$cost          = $cost_calc->calculate( $model, $prompt_tokens, $comp_tokens );

			$total_prompt_tokens     += $prompt_tokens;
			$total_completion_tokens += $comp_tokens;
			$total_cost              += $cost;

			$usage_repo->log_usage( [
				'employee_id'       => $agent_id,
				'model'             => $model,
				'prompt_tokens'     => $prompt_tokens,
				'completion_tokens' => $comp_tokens,
				'cost'              => $cost,
			] );

			// Inject elapsed time in output array for frontend consumption
			if ( is_array( $output ) ) {
				$output['elapsed_seconds'] = $elapsed_seconds;
			}

			$step_name = $step['name'] ?? "Step_" . ($index + 1);
			$results[] = [
				'step'   => $step_name,
				'agent'  => $agent['name'],
				'output' => $output,
			];

			$state[ $step_name ] = $output;
		}

		$total_elapsed_time = round( microtime( true ) - $total_start_time, 2 );

		( new \NexusAI\Workforce\Utils\AuditLogger() )->log( 'workflow_completed', "Completed workflow execution with " . count( $results ) . " steps. Total Cost: $" . number_format($total_cost, 5) );

		return [
			'status'             => 'completed',
			'final_state'        => $state,
			'results'            => $results,
			'total_prompt'       => $total_prompt_tokens,
			'total_completion'   => $total_completion_tokens,
			'total_cost'         => $total_cost,
			'total_elapsed_time' => $total_elapsed_time,
		];
	}

	private function get_orchestrator( array $agent_data ): Orchestrator {
		$settings_repo = new \NexusAI\Workforce\Repositories\SettingsRepository();
		$default_model = $settings_repo->get( 'default_model', 'gpt-4o' );

		$model_settings = json_decode( $agent_data['model_settings'] ?? '{}', true );
		$model_name = $agent_data['model'] ?? $model_settings['model'] ?? $default_model;
		if ( empty( $model_name ) ) {
			$model_name = $default_model;
		}

		// Dynamically determine the provider based on the chosen model name
		$provider = 'openai';
		if ( strpos( $model_name, 'claude' ) !== false ) {
			$provider = 'claude';
		} elseif ( strpos( $model_name, 'gemini' ) !== false ) {
			$provider = 'gemini';
		} elseif ( strpos( $model_name, 'llama' ) !== false || strpos( $model_name, 'openrouter' ) !== false ) {
			$provider = 'openrouter';
		} elseif ( strpos( $model_name, 'local' ) !== false ) {
			$provider = 'ollama';
		}

		$model = ModelFactory::create( $provider );
		return new Orchestrator( $model );
	}
}
