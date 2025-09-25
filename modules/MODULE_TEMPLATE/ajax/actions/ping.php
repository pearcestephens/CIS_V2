<?php
declare(strict_types=1);
/** Example action file for __MODULE_NAME__ */

function mt_ping(): array {
    return [
        'pong' => true,
        'ts' => date('c'),
    ];
}
