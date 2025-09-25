<?php
declare(strict_types=1);
/**
 * modules/transfers/stock/core/TransferService.php
 * Service layer for stock transfer lifecycle operations.
 */

require_once __DIR__ . '/States.php';
require_once __DIR__ . '/QueueProducer.php';

final class TransferService
{
    private QueueProducer $producer;
    // DEV/testing-only lightweight state store (JSON file) to persist state across CLI/script runs
    // This will be replaced by CIS DB wiring in production.
    private string $stateFile;

    public function __construct(?QueueProducer $producer = null)
    {
        $this->producer = $producer ?? new QueueProducer();
        $this->stateFile = realpath(__DIR__ . '/../testing') !== false
            ? (string)realpath(__DIR__ . '/../testing') . '/.state.json'
            : __DIR__ . '/../testing/.state.json';
    }
    /**
     * Create draft transfer.
     * @param int $fromOutlet
     * @param int $toOutlet
     * @param int $userId
     * @return array{transfer_id:int,state:string}
     */
    public function createDraft(int $fromOutlet, int $toOutlet, int $userId): array
    {
        $id = $this->persistDraft($fromOutlet, $toOutlet, $userId);
        $this->setState($id, TransferState::DRAFT, $userId);
        return ['transfer_id' => $id, 'state' => TransferState::DRAFT];
    }

    /**
     * Add or update items while in DRAFT/PACKING.
     * @param int $transferId
     * @param array<int,array{product_id:int,qty:int}> $items
     */
    public function addItems(int $transferId, array $items, int $userId): array
    {
        $state = $this->getState($transferId);
        if (!in_array($state, [TransferState::DRAFT, TransferState::PACKING], true)) {
            throw new RuntimeException('Cannot add items in state ' . $state);
        }
        $this->persistItems($transferId, $items, $userId);
        // Auto-bump to PACKING if still draft
        if ($state === TransferState::DRAFT) {
            $this->setState($transferId, TransferState::PACKING, $userId);
            $state = TransferState::PACKING;
        }
        return ['transfer_id' => $transferId, 'state' => $state];
    }

    /**
     * Mark ready to send.
     */
    public function markReady(int $transferId, int $userId): array
    {
        $state = $this->getState($transferId);
        if (!TransferState::canTransition($state, TransferState::READY_TO_SEND)) {
            throw new RuntimeException('Invalid transition to READY_TO_SEND from ' . $state);
        }
        $this->setState($transferId, TransferState::READY_TO_SEND, $userId);
        // Optionally emit a queue job on finalize (audit-only). Gated for DEV/STAGE.
        if (\QueueConfig::ENABLE_FINALIZE_QUEUE) {
            $idem = $this->idemKey('finalize_notice', $transferId);
            $payload = [
                'transfer_id' => $transferId,
                'state' => TransferState::READY_TO_SEND,
                'summary' => $this->getSummary($transferId),
                'note' => 'finalize_pack',
            ];
            $this->producer->publish(\QueueConfig::FINALIZE_JOB_TYPE, $payload, $idem, [
                'actor_id' => $userId,
                'request_id' => $this->requestId(),
                'audit_only' => true,
            ]);
        }
        return ['transfer_id' => $transferId, 'state' => TransferState::READY_TO_SEND];
    }

    /**
     * Send transfer (create Vend consignment, optionally label and tracking).
     */
    public function send(int $transferId, int $userId, array $shipment = []): array
    {
        $state = $this->getState($transferId);
        if (!in_array($state, [TransferState::READY_TO_SEND, TransferState::PACKING], true)) {
            throw new RuntimeException('Cannot send in state ' . $state);
        }
        // Enqueue status update to IN_TRANSIT (vendor OPEN->IN_TRANSIT)
        $consignmentId = $this->createVendConsignment($transferId, $shipment, $userId); // placeholder until DB wired
        $idem = $this->idemKey('update_consignment_in_transit', $transferId);
        $payload = [
            'consignment_id' => $consignmentId,
            'transfer_id' => $transferId,
            'status' => 'IN_TRANSIT',
            'shipment' => $shipment,
        ];
        $this->producer->publish('update_consignment', $payload, $idem, [
            'actor_id' => $userId,
            'request_id' => $this->requestId(),
        ]);
        $this->setState($transferId, TransferState::SENT, $userId);
        return ['transfer_id' => $transferId, 'state' => TransferState::SENT, 'vend_consignment_id' => $consignmentId];
    }

    /**
     * Receive (partial or final) against the Vend consignment.
     */
    public function receive(int $transferId, int $userId, array $items, bool $final = false): array
    {
        $state = $this->getState($transferId);
        if (!in_array($state, [TransferState::SENT, TransferState::IN_TRANSIT, TransferState::RECEIVING, TransferState::PARTIALLY_RECEIVED], true)) {
            throw new RuntimeException('Cannot receive in state ' . $state);
        }
        $this->applyReceive($transferId, $items, $userId, $final);
        $newState = $final ? TransferState::RECEIVED : TransferState::PARTIALLY_RECEIVED;
        $this->setState($transferId, $newState, $userId);
        // Emit update_consignment to reflect partial lines or final RECEIVED status
        $type = 'update_consignment';
        $idem = $this->idemKey($final ? 'update_consignment_received' : 'update_consignment_partial', $transferId, $items);
        $payload = [
            'transfer_id' => $transferId,
            'lines' => $items,
        ];
        if ($final) { $payload['status'] = 'RECEIVED'; }
        $this->producer->publish($type, $payload, $idem, [
            'actor_id' => $userId,
            'request_id' => $this->requestId(),
        ]);
        return ['transfer_id' => $transferId, 'state' => $newState];
    }

    public function cancel(int $transferId, int $userId): array
    {
        $state = $this->getState($transferId);
        if ($state === TransferState::RECEIVED) {
            throw new RuntimeException('Cannot cancel a received transfer');
        }
        $this->setState($transferId, TransferState::CANCELED, $userId);
        return ['transfer_id' => $transferId, 'state' => TransferState::CANCELED];
    }

    public function status(int $transferId): array
    {
        return [
            'transfer_id' => $transferId,
            'state' => $this->getState($transferId),
            'summary' => $this->getSummary($transferId),
        ];
    }

    // --- Persistence & integration placeholders (to be wired to CIS DB + Vend queue) ---
    private function persistDraft(int $fromOutlet, int $toOutlet, int $userId): int { return random_int(10000, 99999); }

    private function getState(int $transferId): string {
        $all = $this->loadState();
        $state = $all[$transferId]['state'] ?? null;
        if (is_string($state) && $state !== '') { return $state; }
        // Default unknown transfers to PACKING for DEV testing
        return TransferState::PACKING;
    }

    private function setState(int $transferId, string $state, int $userId): void {
        $all = $this->loadState();
        if (!isset($all[$transferId])) { $all[$transferId] = []; }
        $all[$transferId]['state'] = $state;
        $all[$transferId]['updated_by'] = $userId;
        $all[$transferId]['updated_at'] = date('c');
        $this->saveState($all);
    }

    private function persistItems(int $transferId, array $items, int $userId): void {
        $all = $this->loadState();
        if (!isset($all[$transferId])) { $all[$transferId] = []; }
        $all[$transferId]['items'] = $items;
        $all[$transferId]['items_updated_by'] = $userId;
        $all[$transferId]['items_updated_at'] = date('c');
        $this->saveState($all);
    }

    private function createVendConsignment(int $transferId, array $shipment, int $userId): string {
        // For DEV testing, store shipment meta
        $all = $this->loadState();
        if (!isset($all[$transferId])) { $all[$transferId] = []; }
        $all[$transferId]['shipment'] = $shipment;
        $all[$transferId]['shipment_set_by'] = $userId;
        $all[$transferId]['shipment_set_at'] = date('c');
        $this->saveState($all);
        return 'vend_' . $transferId;
    }

    private function applyReceive(int $transferId, array $items, int $userId, bool $final): void {
        $all = $this->loadState();
        if (!isset($all[$transferId])) { $all[$transferId] = []; }
        $all[$transferId]['received'] = $items;
        $all[$transferId]['receive_final'] = $final;
        $all[$transferId]['received_by'] = $userId;
        $all[$transferId]['received_at'] = date('c');
        $this->saveState($all);
    }

    private function getSummary(int $transferId): array {
        $all = $this->loadState();
        $items = $all[$transferId]['items'] ?? [];
        $count = 0; foreach ($items as $it) { $count += (int)($it['qty'] ?? 0); }
        return ['items' => $count, 'packages' => 1];
    }

    private function loadState(): array {
        $file = $this->stateFile;
        if (!is_file($file)) { return []; }
        $json = file_get_contents($file);
        if ($json === false || $json === '') { return []; }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function saveState(array $data): void {
        $file = $this->stateFile;
        $dir = dirname($file);
        // Write only if testing directory already exists (avoid creating files in production)
        if (!is_dir($dir)) { return; }
        $tmp = $file . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) { return; }
        $fp = fopen($tmp, 'wb');
        if ($fp !== false) {
            // best-effort lock
            @flock($fp, LOCK_EX);
            fwrite($fp, $json);
            fflush($fp);
            @flock($fp, LOCK_UN);
            fclose($fp);
            @rename($tmp, $file);
        }
    }

    private function idemKey(string $type, int $transferId, $items = null): string
    {
        $base = $type . ':' . $transferId;
        if ($items !== null) {
            $base .= ':' . substr(hash('sha256', json_encode($items)), 0, 12);
        }
        return $base;
    }

    private function requestId(): string
    {
        $rid = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        if ($rid) { return (string)$rid; }
        return bin2hex(random_bytes(8));
    }

    /**
     * Return a current state snapshot for auditing (DEV/testing only).
     * @return array|null
     */
    public function snapshot(int $transferId): ?array
    {
        $all = $this->loadState();
        if (!isset($all[$transferId])) { return null; }
        // Limit snapshot size by selecting key fields
        $row = $all[$transferId];
        return [
            'state' => $row['state'] ?? null,
            'items' => $row['items'] ?? null,
            'shipment' => $row['shipment'] ?? null,
            'received' => $row['received'] ?? null,
            'receive_final' => $row['receive_final'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'items_updated_at' => $row['items_updated_at'] ?? null,
            'shipment_set_at' => $row['shipment_set_at'] ?? null,
            'received_at' => $row['received_at'] ?? null,
        ];
    }
}
