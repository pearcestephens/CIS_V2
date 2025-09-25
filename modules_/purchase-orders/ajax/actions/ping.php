<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success'=>true,'data'=>['pong'=>true,'ts'=>time()]]);
