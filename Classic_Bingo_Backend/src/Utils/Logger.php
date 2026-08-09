<?php 

namespace App\Utils;

/**
 * A simple, static, file-based logger for the application.
 *
 * This utility class provides a centralized way to log informational messages
 * and errors. 
 */
class Logger{

    // STRING CONSTANTS .. 

    /**
     * @var string The date format for log timestamps.
     */
    private const DATA_TIME_FORMATE = 'Y-m-d H:i:s';

    /**
     * @var string The log level for informational messages.
     */
    private const INFO_LOG_LEVEL = 'INFO';

    /**
     * @var string The log level for error messages.
     */
    private const ERROR_LOG_LEVEL = 'ERROR';

    private const  WARNING_LOG_LEVEL = 'WARNING';

    /**
     * Holds the absolute path to the log file.
     * It is null until the logger is initialized.
     * @var string|null
     */
    private static ?string $logFile = null;

    /**
     * Initializes the logger with a specific file path.
     *
     * @param string $logFilePath The absolute path to the log file.
     * @return void
     */
    public static function init(string $logFilePath): void{

        // Ensure the directory for the log file exists.
        $logDir = dirname($logFilePath);
        if(!is_dir($logDir)){
            // 0775: Directory permissions: owner and group can read/write/execute, others can read/execute.
            // true: The recursive flag allows it to create parent directories if needed.
            mkdir($logDir, 0775, true); 
        }
        self::$logFile = $logFilePath;
    }

    /**
     * The core logging method that writes a formatted message to the log file.
     *
     * @param string $level   The severity level of the log entry (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param array<string, mixed>  $context Optional array of contextual data to be JSON encoded and appended.
     * @return void
     */
    public static function log(string $level, string $message, array $context = []):void{
        // Do not attempt to log if the logger has not been initialized.
        if(self::$logFile === null){
            return;
        }  

        $timestamp = date(self::DATA_TIME_FORMATE);
        $logEntry = "[{$timestamp}] [{$level}]: {$message}";

        if(!empty($context)){
            $logEntry .= " " . json_encode($context);
        }
        $logEntry .= PHP_EOL; // Append a newline character.

        // Use PHP's built-in error_log function to append the message to the specified file.
        // The '3' indicates that the message should be appended to the destination file.
        error_log($logEntry, 3, self::$logFile);
    }

    /**
     * A convenience method for logging informational messages.
     *
     * @param string $message The log message.
     * @param array<string, mixed>  $context Optional array of contextual data.
     * @return void
     */
    public static function info(string $message, array $context = []):void{
        self::log(self::INFO_LOG_LEVEL, $message, $context);
    }

    /**
     * A convenience method for logging warning messages.
     *
     * @param string $message The log message.
     * @param array<string, mixed>  $context Optional array of contextual data.
     * @return void
     */
    public static function warning(string $message, array $context = []):void{
        self::log(self::WARNING_LOG_LEVEL, $message, $context);
    }

    /**
     * A convenience method for logging error messages.
     *
     * @param string $message The log message.
     * @param array<string, mixed>  $context Optional array of contextual data.
     * @return void
     */
    public static function error(string $message, array $context = []):void{
        self::log(self::ERROR_LOG_LEVEL, $message, $context);
    }
}