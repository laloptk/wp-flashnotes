<?php

namespace WPFlashNotes\BaseClasses;

defined('ABSPATH') || exit;

abstract class BaseTable
{
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    protected \wpdb $wpdb;

    /**
     * Table slug without prefix.
     *
     * @var string|null
     */
    protected ?string $slug = null;

    /**
     * Constructor.
     * Assigns the global $wpdb instance.
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Creates or updates the table schema using dbDelta().
     *
     * Executes before/after hooks and logs the dbDelta result.
     *
     * @throws \RuntimeException If the table slug is missing or invalid.
     * @return void
     */
    public function install_table(): void
    {
        $table_name = $this->get_table_name();

        do_action('wpfn_before_table_install', $table_name);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql    = $this->get_schema();
        $result = dbDelta($sql);

        if (is_array($result) && !empty($result)) {
            error_log(
                '[WPFlashNotes][dbDelta] ' . $table_name . ' -> ' . implode(' | ', array_values($result))
            );
        }

        do_action('wpfn_after_table_install', $table_name, $result ?? []);
    }

    /**
     * Returns the full table name with the WordPress prefix.
     *
     * @throws \RuntimeException If the slug is missing or invalid.
     * @return string
     */
    public function get_table_name(): string
    {
        if (empty($this->slug) || !is_string($this->slug)) {
            throw new \RuntimeException(sprintf(
                '%s: $slug must be a non-empty string in the child class.',
                static::class
            ));
        }

        if (!$this->is_valid_identifier($this->slug)) {
            throw new \RuntimeException(sprintf(
                '%s: Invalid table slug "%s".',
                static::class,
                $this->slug
            ));
        }

        return $this->wpdb->prefix . $this->slug;
    }

    /**
     * Returns the charset and collation string for CREATE TABLE.
     *
     * @return string
     */
    protected function get_charset_collate(): string
    {
        return $this->wpdb->get_charset_collate();
    }

    /**
     * Checks whether the table exists in the current database.
     *
     * @return bool
     */
    protected function table_exists(): bool
    {
        $table = $this->get_table_name();
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW TABLES LIKE %s", $table)
        );

        return $found === $table;
    }

    /**
     * Validates a SQL identifier (table, column, index name).
     *
     * @param string $name
     * @return bool
     */
    protected function is_valid_identifier(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name);
    }

    /**
     * Returns the CREATE TABLE statement for dbDelta().
     * Must include the full table name via get_table_name()
     * and end with ENGINE and charset/collation.
     *
     * @return string
     */
    abstract protected function get_schema(): string;
}
