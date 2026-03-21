<?php

class SSE {
    public static function init(): void {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (ob_get_level()) ob_end_clean();
        set_time_limit(0);
    }

    public static function send(string $event, array $data): void {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
    }

    public static function keepAlive(): void {
        echo ": keepalive\n\n";
        flush();
    }

    public static function close(): void {
        echo "event: done\n";
        echo "data: {}\n\n";
        flush();
    }
}
