<?php 

namespace WPFlashNotes\BaseClasses;

use Throwable;
use WPFlashNotes\DataBase\TableBuilder;
use WPFlashNotes\Schema\SchemaStrategyInterface;

abstract class BaseTable {

	protected \wpdb $wpdb;
	protected ?string $slug = null;
	protected ?SchemaStrategyInterface $strategy = null;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public function install_table(): bool {
		$table = $this->get_table_name();
		do_action( 'wpfn_before_table_install', $table );

		try {
			if ( ! $this->strategy ) {
				throw new \RuntimeException( static::class . ' has no schema strategy.' );
			}
			$ok = $this->strategy->install( $this );
			do_action( 'wpfn_after_table_install', $table, $ok );
			return $ok;
		} catch ( Throwable $e ) {
			error_log( sprintf(
				'[WPFlashNotes] Table install failed for %s (%s): %s',
				$table,
				static::class,
				$e->getMessage()
			));
			do_action( 'wpfn_table_install_error', $table, $e );
			return false;
		}
	}

	public function get_table_name(): string {
		if ( empty( $this->slug ) || ! $this->is_valid_identifier( $this->slug ) ) {
			throw new \RuntimeException( sprintf( 'Invalid $slug in %s', static::class ) );
		}
		return $this->wpdb->prefix . $this->slug;
	}

	protected function is_valid_identifier( string $n ): bool {
		return (bool) preg_match( '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $n );
	}

	public function get_charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	abstract public function define_schema( mixed $builder ): mixed;
}
