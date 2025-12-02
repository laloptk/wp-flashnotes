<?php
namespace WPFlashNotes\BaseClasses;

defined( 'ABSPATH' ) || exit;

use Throwable;
use WPFlashNotes\DataBase\Schema\SchemaStrategyInterface;
use WPFlashNotes\DataBase\TableBuilder;

/**
 * BaseTable
 *
 * Abstract base for all custom tables.
 * Uses a pluggable schema strategy (dbDelta or TableBuilder).
 */
abstract class BaseTable {

	protected \wpdb $wpdb;

	/**
	 * Un-prefixed table slug, e.g. 'wpfn_cards'.
	 * Subclasses must override this.
	 *
	 * @var string|null
	 */
	protected ?string $slug = null;

	/**
	 * Strategy responsible for installing/updating the schema.
	 *
	 * @var SchemaStrategyInterface|null
	 */
	protected ?SchemaStrategyInterface $strategy = null;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Inject the schema strategy (DbDeltaStrategy, TableBuilderStrategy, etc.).
	 */
	public function set_strategy( SchemaStrategyInterface $strategy ): void {
		$this->strategy = $strategy;
	}

	/**
	 * Install or update table using the configured strategy.
	 */
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
			) );

			do_action( 'wpfn_table_install_error', $table, $e );

			return false;
		}
	}

	/**
	 * Full table name with WordPress prefix.
	 */
	public function get_table_name(): string {
		if ( empty( $this->slug ) || ! $this->is_valid_identifier( $this->slug ) ) {
			throw new \RuntimeException( sprintf( 'Invalid $slug in %s', static::class ) );
		}

		return $this->wpdb->prefix . $this->slug;
	}

	/**
	 * Simple SQL identifier validator (table/column/index names).
	 */
	protected function is_valid_identifier( string $n ): bool {
		return (bool) preg_match( '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $n );
	}

	public function get_charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	public function define_dbdelta_schema(): string {
		throw new \RuntimeException(
			static::class . ' does not support dbDelta schema strategy.'
		);
	}

	public function define_builder_schema( TableBuilder $builder ): void {
		throw new \RuntimeException(
			static::class . ' does not support TableBuilder schema strategy.'
		);
	}
}
