<?php
/**
 * Asraa CRM Logger
 * Custom logging system independent of WordPress debug.log
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Asraa_CRM_Logger' ) ) :

class Asraa_CRM_Logger {

    const SEVERITY_FATAL      = 'fatal';
    const SEVERITY_ERROR      = 'error';
    const SEVERITY_WARNING    = 'warning';
    const SEVERITY_NOTICE     = 'notice';
    const SEVERITY_DEPRECATED = 'deprecated';
    const SEVERITY_INFO       = 'info';
    const SEVERITY_DEBUG      = 'debug';

    protected static $channels = array(
        'php', 'sql', 'ajax', 'rest', 'controller', 'repository',
        'service', 'database', 'file', 'upload', 'auth', 'plugin', 'system',
    );

    public static function log_dir() {
        $dir = defined( 'ASRAA_CRM_LOG_DIR' ) ? ASRAA_CRM_LOG_DIR : ( ( defined( 'ASRAA_CRM_PATH' ) ? ASRAA_CRM_PATH : plugin_dir_path( __FILE__ ) . '../../' ) . 'logs' );
        if ( ! is_dir( $dir ) ) {
            @wp_mkdir_p( $dir );
            @file_put_contents( $dir . '/.htaccess', "Order Deny,Allow\nDeny from all\n" );
            @file_put_contents( $dir . '/index.html', '' );
        }
        return $dir;
    }

    public static function log( $severity, $module, $message, $file = '', $line = 0, $stack_trace = '', $suggested_fix = '' ) {
        $severity = strtolower( (string) $severity );
        $module   = (string) $module;
        $ts       = gmdate( 'Y-m-d H:i:s' );
        if ( empty( $stack_trace ) ) {
            $stack_trace = self::format_backtrace( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) );
        }
        if ( empty( $suggested_fix ) ) {
            $suggested_fix = self::guess_fix( $message );
        }
        $entry = array(
            'timestamp'     => $ts,
            'module'        => $module,
            'severity'      => $severity,
            'file'          => $file,
            'line'          => (int) $line,
            'message'       => (string) $message,
            'stack_trace'   => $stack_trace,
            'suggested_fix' => $suggested_fix,
        );
        $line_json = wp_json_encode( $entry );
        $files = array(
            self::log_dir() . '/asraa-crm.log',
            self::log_dir() . '/' . $severity . '.log',
        );
        foreach ( $files as $lf ) {
            @file_put_contents( $lf, $line_json . PHP_EOL, FILE_APPEND | LOCK_EX );
        }
        return $entry;
    }

    protected static function format_backtrace( $bt ) {
        $out = array();
        foreach ( $bt as $i => $frame ) {
            $f = isset( $frame['file'] ) ? $frame['file'] : '[internal]';
            $l = isset( $frame['line'] ) ? $frame['line'] : 0;
            $fn = ( isset( $frame['class'] ) ? $frame['class'] . $frame['type'] : '' ) . ( isset( $frame['function'] ) ? $frame['function'] : '' );
            $out[] = '#' . $i . ' ' . $f . ':' . $l . ' ' . $fn . '()';
        }
        return implode( "\n", $out );
    }

    protected static function guess_fix( $message ) {
        $m = strtolower( (string) $message );
        if ( strpos( $m, 'undefined function' ) !== false )    return 'Ensure the file that declares this function is required, or wrap the call in function_exists().';
        if ( strpos( $m, 'undefined method' ) !== false )      return 'Verify the class declaration is loaded and the method name matches.';
        if ( strpos( $m, 'undefined variable' ) !== false )    return 'Initialize the variable before use, or check isset().';
        if ( strpos( $m, 'class' ) !== false && strpos( $m, 'not found' ) !== false ) return 'Add require_once for the class file or verify autoloader mapping.';
        if ( strpos( $m, 'headers already sent' ) !== false )  return 'Remove whitespace/BOM before <?php tag and echoes before header calls.';
        if ( strpos( $m, 'deprecated' ) !== false )            return 'Replace the deprecated call with the PHP 8.3-safe alternative.';
        if ( strpos( $m, 'sql' ) !== false )                   return 'Check the SQL statement, table name and prepared parameter types.';
        if ( strpos( $m, 'table' ) !== false && strpos( $m, "doesn't exist" ) !== false ) return 'Run the plugin activation to (re)create the missing table via dbDelta.';
        return 'Inspect the file:line reported above and the stack trace for the root cause.';
    }

    public static function read( $filter = array(), $limit = 500 ) {
        $file = self::log_dir() . '/asraa-crm.log';
        if ( ! file_exists( $file ) ) return array();
        $lines = @file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( ! is_array( $lines ) ) return array();
        $lines = array_reverse( $lines );
        $rows = array();
        foreach ( $lines as $ln ) {
            $row = json_decode( $ln, true );
            if ( ! is_array( $row ) ) continue;
            if ( ! empty( $filter['severity'] ) && $row['severity'] !== $filter['severity'] ) continue;
            if ( ! empty( $filter['module'] ) && stripos( $row['module'], $filter['module'] ) === false ) continue;
            if ( ! empty( $filter['search'] ) && stripos( $row['message'], $filter['search'] ) === false && stripos( $row['file'], $filter['search'] ) === false ) continue;
            $rows[] = $row;
            if ( count( $rows ) >= (int) $limit ) break;
        }
        return $rows;
    }

    public static function clear() {
        $dir = self::log_dir();
        foreach ( (array) glob( $dir . '/*.log' ) as $f ) {
            @unlink( $f );
        }
        return true;
    }

    public static function download_path() {
        return self::log_dir() . '/asraa-crm.log';
    }

    public static function counts_by_severity() {
        $file = self::log_dir() . '/asraa-crm.log';
        $counts = array( 'fatal' => 0, 'error' => 0, 'warning' => 0, 'notice' => 0, 'deprecated' => 0, 'info' => 0, 'debug' => 0 );
        if ( ! file_exists( $file ) ) return $counts;
        $fh = @fopen( $file, 'r' );
        if ( ! $fh ) return $counts;
        while ( ( $line = fgets( $fh ) ) !== false ) {
            $row = json_decode( $line, true );
            if ( is_array( $row ) && isset( $row['severity'], $counts[ $row['severity'] ] ) ) {
                $counts[ $row['severity'] ]++;
            }
        }
        fclose( $fh );
        return $counts;
    }
}

endif;

if ( ! function_exists( 'asraa_crm_log' ) ) {
    function asraa_crm_log( $severity, $module, $message, $file = '', $line = 0, $stack_trace = '', $suggested_fix = '' ) {
        if ( class_exists( 'Asraa_CRM_Logger' ) ) {
            return Asraa_CRM_Logger::log( $severity, $module, $message, $file, $line, $stack_trace, $suggested_fix );
        }
        return null;
    }
}
