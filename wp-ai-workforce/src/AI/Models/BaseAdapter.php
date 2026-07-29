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

		// Detect if we are in a workflow and extract task & trigger details
		$workflow_task = '';
		if ( preg_match( '/Current Task:\s*([^\n]+)/i', $user_msg, $task_matches ) ) {
			$workflow_task = trim( $task_matches[1] );
		}

		$initial_trigger = '';
		if ( preg_match( '/\[initial_trigger\]:\s*([^\n]+)/i', $user_msg, $it_matches ) ) {
			$initial_trigger = trim( $it_matches[1] );
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

		// Extract actual agenda content or chairman intervention content from user message for more accurate topic extraction
		$agenda_content = '';
		if ( preg_match( '/AGENDA:\s*(.*?)(?=\.?\s*As the)/is', $user_msg, $agenda_matches ) ) {
			$agenda_content = trim( $agenda_matches[1] );
		}

		// Detect if there is a chairman intervention message and split from strategic agenda
		$chairman_intervention = '';
		$strategic_agenda_text = $agenda_content;
		if ( ! empty( $agenda_content ) ) {
			if ( preg_match( '/(.*?)\s*CHAIRMAN INTERVENTION:\s*(.*)/is', $agenda_content, $split_matches ) ) {
				$strategic_agenda_text = trim( $split_matches[1] );
				$chairman_intervention = trim( $split_matches[2] );
			}
		}

		if ( empty( $chairman_intervention ) && preg_match( '/CHAIRMAN INTERVENTION:\s*([^\n]+)/i', $user_msg, $ch_matches ) ) {
			$chairman_intervention = trim( $ch_matches[1] );
		}
		if ( ! empty( $chairman_intervention ) ) {
			$chairman_intervention = preg_replace( '/\s*Please think step-by-step before providing your final answer\..*/s', '', $chairman_intervention );
			$chairman_intervention = trim( $chairman_intervention );
		}

		// If we are in a workflow, use the initial trigger as the topic source. Otherwise, use agenda content or clean user message.
		if ( ! empty( $initial_trigger ) ) {
			$topic_source = $initial_trigger;
		} else {
			$topic_source = ! empty( $agenda_content ) ? $agenda_content : $clean_user_msg;
		}

		// Extract topic words from clean topic source for smart contextual reflection
		$topic_words = [];
		if ( preg_match_all( '/\b[a-zA-Z]{4,15}\b/', $topic_source, $matches ) ) {
			$ignored_words = [ 'with', 'this', 'that', 'your', 'from', 'have', 'would', 'should', 'could', 'about', 'there', 'their', 'them', 'then', 'here', 'some', 'please', 'think', 'step', 'final', 'answer', 'structure', 'output', 'chairman', 'intervention', 'meeting', 'round', 'strategic', 'agenda', 'expert', 'opinion', 'contribution', 'goal', 'decision', 'action', 'professional', 'concise', 'focused', 'specific', 'role', 'kpis' ];
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

		if ( ! empty( $initial_trigger ) ) {
			$lower_trigger = strtolower( $initial_trigger );
			$is_roi = ( strpos( $lower_trigger, 'roi' ) !== false || strpos( $lower_trigger, 'audit' ) !== false || strpos( $lower_trigger, 'cost' ) !== false || strpos( $lower_trigger, 'pricing' ) !== false || strpos( $lower_trigger, 'refund' ) !== false || strpos( $lower_trigger, 'budget' ) !== false || strpos( $lower_trigger, 'revenue' ) !== false || strpos( $lower_trigger, 'sales' ) !== false );
			$is_marketing = ( strpos( $lower_trigger, 'market' ) !== false || strpos( $lower_trigger, 'growth' ) !== false || strpos( $lower_trigger, 'ad' ) !== false || strpos( $lower_trigger, 'seo' ) !== false || strpos( $lower_trigger, 'copy' ) !== false || strpos( $lower_trigger, 'headline' ) !== false || strpos( $lower_trigger, 'storm' ) !== false || strpos( $lower_trigger, 'brand' ) !== false );
			$is_system = ( strpos( $lower_trigger, 'security' ) !== false || strpos( $lower_trigger, 'scale' ) !== false || strpos( $lower_trigger, 'cache' ) !== false || strpos( $lower_trigger, 'database' ) !== false || strpos( $lower_trigger, 'api' ) !== false || strpos( $lower_trigger, 'code' ) !== false || strpos( $lower_trigger, 'system' ) !== false || strpos( $lower_trigger, 'latency' ) !== false || strpos( $lower_trigger, 'technical' ) !== false || strpos( $lower_trigger, 'debt' ) !== false || strpos( $lower_trigger, 'architecture' ) !== false || strpos( $lower_trigger, 'rag' ) !== false );
		} else {
			$is_roi       = strpos( $lower_msg, 'roi' ) !== false || strpos( $lower_msg, 'audit' ) !== false || strpos( $lower_msg, 'cost' ) !== false || strpos( $lower_msg, 'budget' ) !== false || strpos( $lower_msg, 'revenue' ) !== false || strpos( $lower_msg, 'pricing' ) !== false || strpos( $lower_msg, 'sales' ) !== false;
			$is_marketing = strpos( $lower_msg, 'market' ) !== false || strpos( $lower_msg, 'growth' ) !== false || strpos( $lower_msg, 'ad' ) !== false || strpos( $lower_msg, 'storm' ) !== false || strpos( $lower_msg, 'brand' ) !== false || strpos( $lower_msg, 'copy' ) !== false || strpos( $lower_msg, 'headline' ) !== false;
			$is_system    = strpos( $lower_msg, 'security' ) !== false || strpos( $lower_msg, 'scale' ) !== false || strpos( $lower_msg, 'latency' ) !== false || strpos( $lower_msg, 'technical' ) !== false || strpos( $lower_msg, 'debt' ) !== false || strpos( $lower_msg, 'architecture' ) !== false || strpos( $lower_msg, 'rag' ) !== false || strpos( $lower_msg, 'code' ) !== false || strpos( $lower_msg, 'database' ) !== false || strpos( $lower_msg, 'api' ) !== false;
		}

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
					"Hey team! {$agent_name} here. If we are looking to optimize our ROI for '{$extracted_topic}', we absolutely need first-principles execution. For example, if we are overpaying for external APIs or redundant SaaS licenses, we should consolidate them into a single enterprise custom model. Let's eliminate high-overhead busywork, align our resources strictly behind revenue-driving initiatives, and establish clear accountability across all workspaces.
WARNING: We face a serious risk of alignment drift and metrics silos if departments operate independently.
BEST SOLUTION: Establish a central, real-time command dashboard so every executive can instantly monitor shared token usage and active workflows.",

					"We need to look closely at where our capital is going regarding '{$extracted_topic}'. To get the best margins, we should prune any processes that don't add direct enterprise value. A key common-sense example is automating manual report collection which currently wastes 15 hours a week per manager, and instead track progress using precise, automated KPIs.
WARNING: Attempting to measure KPIs manually introduces severe human bias and reporting delays.
BEST SOLUTION: Deploy direct database trigger listeners that auto-calculate and stream ROI metrics straight to our strategic transcripts.",

					"Let's keep our execution roadmap for '{$extracted_topic}' exceptionally clean and high-margin. For instance, prioritizing organic search traffic over costly paid acquisition has historically yielded a 3x higher lifetime value. We must focus our energy on our most profitable operations, cut down unnecessary overhead, and establish an unshakeable, clear path to success.
WARNING: Organic content loops have a slow start and can leave a revenue gap in the first 90 days.
BEST SOLUTION: Use a dual-speed model where high-intent paid retargeting supplements organic checklists to maintain steady short-term conversions."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'marketing' ) !== false || strpos( $lower_pos, 'growth' ) !== false || strpos( $lower_pos, 'cmo' ) !== false ) {
				$templates = [
					"Lively ideas here, team! {$agent_name} chiming in. To scale our growth loops on '{$extracted_topic}', we must stop wasting cash on untracked vanity campaigns. For instance, instead of spending $5k/month on untargeted brand ads, let's double down on high-intent search keywords and A/B test our headlines to increase CTR by 20%. Let's look closely at our CAC/LTV ratio.
WARNING: Sudden bid adjustments on competitive search keywords can temporarily inflate our CAC.
BEST SOLUTION: Enforce strict automated bidding guardrails that pause search ad campaigns whenever day-to-day keyword acquisition costs exceed our designated target by more than 15%.",

					"We can drive massive growth and higher conversions for '{$extracted_topic}' by auditing our current promotional messaging. For example, replacing generic 'learn more' buttons with benefit-driven copy like 'Get Instant ROAS Analysis' has proven to boost sign-ups. Let's make sure our value proposition is incredibly easy for anyone to understand and only allocate our ad budget to channels with a proven, positive ROAS.
WARNING: Increasing copy variations on active landing pages can fragment our conversion tracking data.
BEST SOLUTION: Deploy unified cookies or server-side session hashes to track the entire customer journey and accurately assign attribution.",

					"Let's look at the customer journey for '{$extracted_topic}'. Common sense says that retaining an existing client is 5x cheaper than acquiring a new one. By focusing our marketing efforts on retaining happy, repeat buyers through personalized email flows and simplifying our checkout steps, we can boost our recurring revenue without increasing ad spend.
WARNING: Overloading loyal buyers with repetitive or dense marketing sequences can trigger severe email fatigue and high unsubscribe rates.
BEST SOLUTION: Segment our subscriber database based on purchase recency and only trigger highly relevant, tailored retention offers once every 14 days."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} elseif ( strpos( $lower_pos, 'system' ) !== false || strpos( $lower_pos, 'engineer' ) !== false || strpos( $lower_pos, 'developer' ) !== false || strpos( $lower_pos, 'cto' ) !== false || strpos( $lower_pos, 'technical' ) !== false ) {
				$templates = [
					"Hey everyone, {$agent_name} here. System stability and speed are the foundation for '{$extracted_topic}'. I'm auditing our backend; if database queries are bloated, we lose money. For example, replacing a slow, unindexed custom table lookup with a transients cache can slash latency by 300ms. Let's optimize caching, streamline data structures, and keep this platform running like lightning.
WARNING: Over-reliance on object caching without strict cache busting can lead to stale admin settings being displayed.
BEST SOLUTION: Implement specific cache-key hooks on save_post and update_option to automatically invalidate and refresh transient states.",

					"We can lower our operational tech stack costs for '{$extracted_topic}' by keeping our codebase simple, elegant, and secure. For instance, avoiding over-engineered microservices and utilizing native WordPress transient caching keeps server resource consumption low and ensures frictionless maintenance.
WARNING: Monolithic code structures can make isolated debugging incredibly difficult as the plugin scales.
BEST SOLUTION: Adopt strict modular design patterns, isolating custom REST controllers and data-access repositories to ensure clean API scaling.",

					"Let's focus on system efficiency for '{$extracted_topic}'. By optimizing external API lookups and cleaning up redundant scripts, we can slash server response times. As a concrete example, implementing asynchronous batching on outbound REST requests reduces the overall processing queue bottleneck. Let's deliver an ultra-responsive user experience.
WARNING: Concurrent asynchronous requests can trigger external API rate-limit penalties.
BEST SOLUTION: Build an elegant internal queue throttling engine with token bucket pacing to stay completely under vendor rate limits."
				];
				$reply_body = $templates[ $hash % count($templates) ];
			} else {
				$templates = [
					"From my standpoint as {$agent_position}, we can maximize returns for '{$extracted_topic}' by focusing on simplified, high-priority objectives. Let's keep our execution direct and cut out any fluff.
WARNING: Over-simplifying workflows can occasionally overlook critical edge-case security checks.
BEST SOLUTION: Maintain a basic, automated security checklist for all custom routes to ensure peace of mind.",

					"To optimize cost-efficiency here, we should eliminate redundant meetings and establish straightforward milestones for '{$extracted_topic}' that keep us directly on track.
WARNING: Complete elimination of meetings can impact long-term team collaboration and synergy.
BEST SOLUTION: Establish quick 5-minute daily asynchronous slack updates to maintain high collaboration without scheduling overhead.",

					"I recommend a quick operational audit of our resources for '{$extracted_topic}'. Ensuring our workflows are simple and lean will automatically boost our delivery margins.
WARNING: Audit-only focus can lead to analysis paralysis, slowing down our active feature deployments.
BEST SOLUTION: Set a strict 48-hour time limit on all technical and operational audits to ensure we shift rapidly back to active delivery."
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

		// Dynamically inject workflow-specific reasoning and response openers
		if ( ! empty( $initial_trigger ) ) {
			array_unshift( $reasoning_steps, "Align my response with the active workflow trigger '{$initial_trigger}' and the task objective '{$workflow_task}' using my {$agent_position} expertise." );

			$workflow_intros = [
				"Processing our workflow trigger '{$initial_trigger}' for the task '{$workflow_task}': ",
				"Directly addressing our initial trigger '{$initial_trigger}' to execute '{$workflow_task}': ",
				"In response to the workflow trigger '{$initial_trigger}', I have analyzed '{$workflow_task}' and recommend: "
			];
			$wf_intro = $workflow_intros[ $hash % count($workflow_intros) ];
			$reply_body = $wf_intro . lcfirst($reply_body);
		}

		// Deeply analyze chairman command and check relevancy to the Strategic Agenda
		if ( ! empty( $chairman_intervention ) ) {
			$is_related = true;
			if ( ! empty( $strategic_agenda_text ) ) {
				$agenda_words = array_filter( explode( ' ', strtolower( $strategic_agenda_text ) ), function($w) { return strlen($w) > 4; } );
				$intervention_words = array_filter( explode( ' ', strtolower( $chairman_intervention ) ), function($w) { return strlen($w) > 4; } );
				$intersect = array_intersect( $agenda_words, $intervention_words );
				$is_related = ! empty( $intersect );
			}

			if ( ! $is_related ) {
				array_unshift( $reasoning_steps, "The chairman's command ('{$chairman_intervention}') has low direct relevancy to our original agenda ('{$strategic_agenda_text}'). Pivoting focus to deeply analyze and prioritize the chairman's directive using my {$agent_position} capability." );

				$openers = [
					"Pivoting to address your direct directive on '{$chairman_intervention}' as our top priority: ",
					"Focusing specifically on your latest instruction regarding '{$chairman_intervention}' (noting the shift from our previous agenda): ",
					"Understood, Chairman. Prioritizing your direct command regarding '{$chairman_intervention}' over the previous focus: "
				];
			} else {
				array_unshift( $reasoning_steps, "Deeply analyze the chairman's command ('{$chairman_intervention}') and verify its high relevancy to the strategic agenda ('{$strategic_agenda_text}'). Synthesizing both for optimal response." );

				$openers = [
					"I hear your instruction about '{$chairman_intervention}', and here is how my department can help simply: ",
					"That makes total sense regarding '{$chairman_intervention}'. To make this happen with maximum clarity: ",
					"I completely agree with the focus on '{$chairman_intervention}'. From my perspective: "
				];
			}
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
