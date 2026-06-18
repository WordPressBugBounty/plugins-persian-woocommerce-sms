<?php

namespace PW\PWSMS\Services;

use Throwable;

class ScheduleService {

	public const HOOK = 'pwsms_schedule_service';
	public const MAX_RETRY = 2;

	public function __construct() {
		add_action( self::HOOK, [ self::class, 'handle' ], 10, 4 );
	}

	public static function dispatch( $callback, $payload = null, string $queue = 'default' ): bool {
		$args = [ $callback, $payload, 0, $queue ];

		$action_id = as_enqueue_async_action( self::HOOK, $args, $queue );

		return boolval( $action_id );
	}

	public static function handle( $callback, $payload = null, int $attempt = 0, string $queue = 'default' ): bool {

		if ( ! is_callable( $callback ) ) {
			error_log( sprintf( '[PWSMS][ScheduleService] callback "%s" is not callable.', is_string( $callback ) ? $callback : gettype( $callback ), ) );

			return false;
		}

		try {

			call_user_func( $callback, $payload );

		} catch ( Throwable $e ) {

			error_log( sprintf( '[PWSMS][ScheduleService] Fail [Attempt %d]: %s', $attempt, $e->getMessage() ) );

			if ( $attempt >= self::MAX_RETRY ) {

				error_log( '[PWSMS][ScheduleService] Max retries exhausted. Job abandoned.' );

				return false;
			}

			return self::schedule_retry( $callback, $payload, $attempt + 1, $queue );
		}

		return true;
	}

	protected static function schedule_retry( $callback, $payload, int $attempt, string $original_queue ): bool {

		$delay       = min( 60 * pow( 2, $attempt - 1 ), 3600 );
		$retry_queue = $original_queue . '_retry';
		$timestamp   = time() + $delay;
		$args        = [ $callback, $payload, $attempt, $original_queue ];

		$action_id = as_schedule_single_action( $timestamp, self::HOOK, $args, $retry_queue );

		return boolval( $action_id );
	}
}
