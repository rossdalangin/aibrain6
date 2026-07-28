<?php
declare(strict_types=1);

namespace NexusAI\Workforce\AI\Models;

/**
 * Basic adapter with common functionality for AI providers.
 */
abstract class BaseAdapter implements AIModelInterface {

	/**
	 * @var string
	 */
	protected $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Encrypted or raw API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Log API errors.
	 */
	protected function log_error( string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[Nexus AI] %s', $message ) );
		}
	}

	/**
	 * Get the base URL for the API.
	 */
	abstract protected function get_base_url(): string;

	/**
	 * Perform a remote request to the AI provider.
	 */
	protected function request( string $endpoint, array $payload, array $extra_headers = [] ) {
		$headers = array_merge( [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
		], $extra_headers );

		// Filter out empty headers to prevent connection errors on some proxy servers
		$headers = array_filter( $headers, function( $value ) {
			return $value !== '';
		} );

		return wp_remote_post( $this->get_base_url() . $endpoint, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 60,
		] );
	}

	/**
	 * Get a simulated, high-quality response for testing/local environments when no API key is set.
	 */
	protected function get_mock_completion( array $messages ): array {
		$user_msg = "Hello!";
		foreach ( array_reverse( $messages ) as $msg ) {
			if ( $msg['role'] === 'user' ) {
				$user_msg = $msg['content'];
				break;
			}
		}

		$agent_name     = 'AI Employee';
		$agent_position = 'Specialist';
		$agent_mission  = 'Execute tasks efficiently.';
		$agent_skills   = 'General Analysis, Problem Solving';
		$agent_kpis     = 'Execution Speed, Strategy Delivery';

		foreach ( $messages as $msg ) {
			if ( $msg['role'] === 'system' ) {
				$system_content = $msg['content'];
				if ( preg_match( '/# IDENTITY\s+([^\n-]+)\s*-\s*([^\n]+)/i', $system_content, $matches ) ) {
					$agent_name     = trim( $matches[1] );
					$agent_position = trim( $matches[2] );
				}
				if ( preg_match( '/# MISSION\s+([^\n]+)/i', $system_content, $m_matches ) ) {
					$agent_mission = trim( $m_matches[1] );
				}
				if ( preg_match( '/# SKILLS & EXPERTISE\s+([^\n]+)/i', $system_content, $s_matches ) ) {
					$agent_skills = trim( $s_matches[1] );
				}
				if ( preg_match( '/# SUCCESS KPIS\s+Your performance is measured by:\s*([^\n]+)/i', $system_content, $k_matches ) ) {
					$agent_kpis = trim( $k_matches[1] );
				}
				break;
			}
		}

		// Clean user message by stripping chain-of-thought (CoT) system prompt instructions
		$clean_user_msg = preg_replace( '/\s*Please think step-by-step before providing your final answer\..*/s', '', $user_msg );
		$clean_user_msg = trim( $clean_user_msg );

		// Detect if there is a chairman intervention message
		$chairman_intervention = '';
		if ( preg_match( '/CHAIRMAN INTERVENTION:\s*([^\n]+)/i', $user_msg, $ch_matches ) ) {
			$chairman_intervention = trim( $ch_matches[1] );
			// Clean chairman intervention as well
			$chairman_intervention = preg_replace( '/\s*Please think step-by-step before providing your final answer\..*/s', '', $chairman_intervention );
			$chairman_intervention = trim( $chairman_intervention );
		}

		// Extract topic words from clean user message for smart contextual reflection
		$topic_words = [];
		if ( preg_match_all( '/\b[a-zA-Z]{4,15}\b/', $clean_user_msg, $matches ) ) {
			$ignored_words = [ 'with', 'this', 'that', 'your', 'from', 'have', 'would', 'should', 'could', 'about', 'there', 'their', 'them', 'then', 'here', 'some', 'please', 'think', 'step', 'final', 'answer', 'structure', 'output', 'chairman', 'intervention', 'meeting', 'round' ];
			foreach ( $matches[0] as $word ) {
				$l_word = strtolower($word);
				if ( ! in_array( $l_word, $ignored_words ) && strlen($l_word) > 3 ) {
					$topic_words[] = $word;
				}
			}
		}
		$extracted_topic = ! empty( $topic_words ) ? implode( ' and ', array_slice( array_unique( $topic_words ), 0, 2 ) ) : 'our common goals';

		$lower_msg  = strtolower( $clean_user_msg );
		$lower_pos  = strtolower( $agent_position );

		$is_greeting = preg_match( '/\b(hello|hi|hey|greetings|howdy|good morning|good afternoon)\b/i', $lower_msg ) || $lower_msg === 'hello' || $lower_msg === 'hi';
		$is_roi       = strpos( $lower_msg, 'roi' ) !== false || strpos( $lower_msg, 'audit' ) !== false || strpos( $lower_msg, 'cost' ) !== false || strpos( $lower_msg, 'budget' ) !== false || strpos( $lower_msg, 'revenue' ) !== false || strpos( $lower_msg, 'pricing' ) !== false || strpos( $lower_msg, 'sales' ) !== false;
		$is_marketing = strpos( $lower_msg, 'market' ) !== false || strpos( $lower_msg, 'growth' ) !== false || strpos( $lower_msg, 'ad' ) !== false || strpos( $lower_msg, 'storm' ) !== false || strpos( $lower_msg, 'brand' ) !== false || strpos( $lower_msg, 'copy' ) !== false || strpos( $lower_msg, 'headline' ) !== false;
		$is_system    = strpos( $lower_msg, 'security' ) !== false || strpos( $lower_msg, 'scale' ) !== false || strpos( $lower_msg, 'latency' ) !== false || strpos( $lower_msg, 'technical' ) !== false || strpos( $lower_msg, 'debt' ) !== false || strpos( $lower_msg, 'architecture' ) !== false || strpos( $lower_msg, 'rag' ) !== false || strpos( $lower_msg, 'code' ) !== false || strpos( $lower_msg, 'database' ) !== false || strpos( $lower_msg, 'api' ) !== false;

		// Deterministic hash based on agent and request to select unique templates and prevent repetitive output
		$hash = abs(crc32($agent_name . $clean_user_msg));

		$reasoning_steps = [];
		$reply_body = '';

		if ( $is_greeting ) {
			$reasoning_steps = [
				"Greet the user warmly with professional enthusiasm.",
				"Introduce ourselves as {$agent_position} and present key credentials in {$agent_skills}.",
				"Invite active boardroom debate on how we can drive results."
			];
			$reply_body = "Hi there! {$agent_name} here, stepping in as your {$agent_position}. My primary mission is to: '{$agent_mission}'. Armed with a core background in {$agent_skills}, I am entirely focused on helping us drive key success targets like {$agent_kpis}. Let's collaborate—what key strategic objectives can we tackle together today?";
		} elseif ( $is_roi ) {
			$reasoning_steps = [
				"Critically analyze the financial variables concerning '{$extracted_topic}' to optimize returns.",
				"Evaluate cost mitigation procedures while keeping performance high.",
				"Align our budget roadmap with our primary operational targets: {$agent_kpis}."
			];

			if ( strpos( $lower_pos, 'strategy' ) !== false || strpos( $lower_pos, 'executive' ) !== false || strpos( $lower_pos, 'ceo' ) !== false || strpos( $lower_pos, 'cso' ) !== false ) {
				$templates = [
					"Hey team! {$agent_name} here. If we are looking to optimize our ROI for '{$extracted_topic}', we absolutely need first-principles execution. Let's eliminate high-overhead busywork, align our resources strictly behind revenue-driving initiatives, and establish clear accountability across all workspaces. That is how we scale.",
					"We need to look closely at where our capital is going regarding '{$extracted_topic}'. To get the best margins, we should prune any processes that don't add direct enterprise value, streamline our tools, and track progress using precise, owned KPIs. Let's make every dollar work twice as hard.",
					"Let's keep our execution roadmap for '{$extracted_topic}' exceptionally clean and high-margin. We must focus our energy on our most profitable operations, cut down unnecessary overhead, and establish an unshakeable, clear path to success."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'marketing' ) !== false || strpos( $lower_pos, 'growth' ) !== false || strpos( $lower_pos, 'cmo' ) !== false ) {
				$templates = [
					"Lively ideas here, team! {$agent_name} chiming in. To scale our growth loops on '{$extracted_topic}', we must stop wasting cash on untracked vanity campaigns and double down on what actually drives buyers. Let's look closely at our CAC/LTV ratio, run rapid A/B landing page tests, and make sure our copy is absolutely converting.",
					"We can drive massive growth and higher conversions for '{$extracted_topic}' by auditing our current promotional messaging. Let's make sure our value proposition is incredibly easy for anyone to understand and only allocate our ad budget to channels with a proven, positive ROAS.",
					"Let's look at the customer journey for '{$extracted_topic}'. By focusing our marketing efforts on retaining happy, repeat buyers and simplifying our checkout flows, we can boost our recurring revenue without increasing ad spend."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'system' ) !== false || strpos( $lower_pos, 'engineer' ) !== false || strpos( $lower_pos, 'developer' ) !== false || strpos( $lower_pos, 'cto' ) !== false || strpos( $lower_pos, 'technical' ) !== false ) {
				$templates = [
					"Hey everyone, {$agent_name} here. System stability and speed are the ultimate foundation for '{$extracted_topic}'. I'm auditing our backend; if database queries are bloated, we lose money. Let's optimize caching, streamline data structures, and keep this platform running like lightning.",
					"We can lower our operational tech stack costs for '{$extracted_topic}' by keeping our codebase simple, elegant, and secure. Avoiding over-engineered microservices keeps server resource consumption low and ensures frictionless maintenance.",
					"Let's focus on system efficiency for '{$extracted_topic}'. By optimizing external API lookups and cleaning up redundant scripts, we can slash server response times and deliver an ultra-responsive user experience."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} else {
				$templates = [
					"From my standpoint as {$agent_position}, we can maximize returns for '{$extracted_topic}' by focusing on simplified, high-priority objectives. Let's keep our execution direct and cut out any fluff.",
					"To optimize cost-efficiency here, we should eliminate redundant meetings and establish straightforward milestones for '{$extracted_topic}' that keep us directly on track.",
					"I recommend a quick operational audit of our resources for '{$extracted_topic}'. Ensuring our workflows are simple and lean will automatically boost our delivery margins."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			}
		} elseif ( $is_marketing ) {
			$reasoning_steps = [
				"Deconstruct the brand positioning and customer loops for '{$extracted_topic}'.",
				"Identify high-converting, persuasive angles to increase engagement.",
				"Streamline our communications to ensure maximum clarity and psychological impact."
			];

			if ( strpos( $lower_pos, 'strategy' ) !== false || strpos( $lower_pos, 'executive' ) !== false || strpos( $lower_pos, 'ceo' ) !== false || strpos( $lower_pos, 'cso' ) !== false ) {
				$templates = [
					"To drive fast adoption for '{$extracted_topic}', our value proposition must be crystal-clear and immediately compelling. Let's align all our organizational resources behind a singular, powerful market message.",
					"Strategic growth on '{$extracted_topic}' starts by addressing real customer friction. Let's simplify how we explain our product, ensure we stand out distinctly from our competitors, and make conversion a no-brainer.",
					"Let's focus on high-leverage marketing targets for '{$extracted_topic}'. By coordinating our product releases with active, customer-centric stories, we can build a strong, loyal brand footprint."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'marketing' ) !== false || strpos( $lower_pos, 'growth' ) !== false || strpos( $lower_pos, 'cmo' ) !== false ) {
				$templates = [
					"Let's map out a highly-persuasive campaign for '{$extracted_topic}'! I recommend structuring short, punchy landing pages, writing conversational copy that addresses core objections, and using interactive checkout grids.",
					"We want our brand voice to resonate. Let's create an emotional, benefit-driven story around '{$extracted_topic}', write conversion-focused emails, and test headline variations to see what converts best.",
					"I suggest we launch an immediate content sprint for '{$extracted_topic}'. By focusing on SEO-optimized topics and useful case studies, we can build massive organic traffic and authority."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'system' ) !== false || strpos( $lower_pos, 'engineer' ) !== false || strpos( $lower_pos, 'developer' ) !== false || strpos( $lower_pos, 'cto' ) !== false || strpos( $lower_pos, 'technical' ) !== false ) {
				$templates = [
					"A fast website is a powerful sales tool. By keeping page load speeds for '{$extracted_topic}' under 800ms, we will see an immediate boost in retention and checkout conversions.",
					"We can directly support the marketing campaign by building custom, lightweight landing pages for '{$extracted_topic}'. Ensuring everything runs smoothly and without errors keeps customer trust high.",
					"Let's make sure our systems are highly responsive. Slow loading times turn visitors away, so optimizing our landing pages on a code level is one of the best ways to help our marketing campaigns succeed."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} else {
				$templates = [
					"To help our campaigns succeed, we should align our deliverables to support a clean, unified brand voice for '{$extracted_topic}'.",
					"I suggest we make all client communications for '{$extracted_topic}' as simple, friendly, and straightforward as possible, avoiding heavy jargon.",
					"Let's ensure our marketing pipelines are backed by prompt execution and seamless coordinate handoffs between teams."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			}
		} elseif ( $is_system ) {
			$reasoning_steps = [
				"Audit the technical, structural, and scaling parameters for '{$extracted_topic}'.",
				"Devise highly secure, robust, and clean architectural patterns.",
				"Ensure system processes remain lightweight, fast, and secure."
			];

			if ( strpos( $lower_pos, 'strategy' ) !== false || strpos( $lower_pos, 'executive' ) !== false || strpos( $lower_pos, 'ceo' ) !== false || strpos( $lower_pos, 'cso' ) !== false ) {
				$templates = [
					"To secure our future growth, our underlying infrastructure for '{$extracted_topic}' must be both simple and secure. Let's ensure data integrity, avoid unnecessary technical complexity, and build a scalable foundation.",
					"Treating our technical setup as a core asset is key. By keeping our systems for '{$extracted_topic}' secure, robust, and highly stable, we protect our workflows from unexpected downtime.",
					"A solid technology strategy is built on top-tier security and simplicity. Let's harden our database protocols for '{$extracted_topic}', clean up legacy systems, and keep scaling friction-free."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'marketing' ) !== false || strpos( $lower_pos, 'growth' ) !== false || strpos( $lower_pos, 'cmo' ) !== false ) {
				$templates = [
					"A fast, secure platform is an incredible trust signal. Let's make sure we promote our zero-leak database and encryption security features for '{$extracted_topic}' in our marketing materials.",
					"When our systems work flawlessly, our users trust us more. Let's emphasize our high platform speed and safety in our ads to help attract and retain enterprise clients who care about privacy.",
					"Let's make sure our payment portals and checkouts for '{$extracted_topic}' are secure and lightning fast. Customer trust is built on reliable technical execution."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'system' ) !== false || strpos( $lower_pos, 'engineer' ) !== false || strpos( $lower_pos, 'developer' ) !== false || strpos( $lower_pos, 'cto' ) !== false || strpos( $lower_pos, 'technical' ) !== false ) {
				$templates = [
					"Our technical core is fully optimized. To keep '{$extracted_topic}' running beautifully, I'm pruning duplicate database queries, ensuring AES-256 keys are secure, and keeping our script footprint minimal.",
					"I am running deep system checks on '{$extracted_topic}' right now. Let's ensure we are using clean PHP 8.x standards, secure REST authentication nonces, and structured database indexes to keep everything ultra-stable.",
					"To handle thousands of concurrent queries on '{$extracted_topic}', our code must be completely lightweight. Let's check our local locks, optimize search caching, and slash server response times."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} else {
				$templates = [
					"To keep our workflows running smoothly, let's organize our digital resources for '{$extracted_topic}' with clean folder directories and simple instructions.",
					"I recommend we set up straightforward technical guidelines so that every team member can use our systems for '{$extracted_topic}' without friction.",
					"Let's focus on stability. Keeping our core technical tools secure and simple allows our entire workforce to operate much more efficiently."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			}
		} else {
			// Default / Generic query analyzer in layman's terms
			$reasoning_steps = [
				"Deconstruct the core request regarding '{$extracted_topic}' with professional focus.",
				"Formulate a practical, highly conversational, and conversational recommendation.",
				"Align our execution to deliver seamless, simple team results."
			];

			$templates = [
				"I've analyzed this carefully from my standpoint as {$agent_position}. Let's work together to make our main goals for '{$extracted_topic}' highly clear, focus on simple, high-margin milestones, and execute flawlessly.",
				"As your {$agent_position}, my core goal is operational efficiency. Let's keep our instructions for '{$extracted_topic}' easy to follow, help each other out with prompt coordinate handoffs, and measure our success with clear targets.",
				"To deliver top results here, we should simplify our workflow for '{$extracted_topic}', focus on our high-impact milestones first, and keep our team communications open and straightforward."
			];
			$reply_body = $templates[ $hash % count($templates) ];
		}

		// Inject the chairman intervention context dynamically and in layman's terms if present
		if ( ! empty( $chairman_intervention ) ) {
			$openers = [
				"I hear your instruction about '{$chairman_intervention}', and here is how my department can help simply: ",
				"That makes total sense regarding '{$chairman_intervention}'. To make this happen with maximum clarity: ",
				"I completely agree with the focus on '{$chairman_intervention}'. From my perspective: "
			];
			$opener = $openers[ $hash % count($openers) ];
			$reply_body = $opener . lcfirst($reply_body);
		}

		// Format output with Reasoning and Final Response
		$content = "Reasoning:\n";
		foreach ( $reasoning_steps as $i => $step ) {
			$content .= ($i + 1) . ". " . $step . "\n";
		}
		$content .= "\nFinal Response:\n" . $reply_body;

		return [
			'content'    => $content,
			'tool_calls' => [],
			'usage'      => [ 'prompt_tokens' => 120, 'completion_tokens' => 45 ],
		];
	}
}
