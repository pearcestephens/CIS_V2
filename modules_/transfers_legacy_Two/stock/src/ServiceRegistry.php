<?php declare(strict_types=1);

namespace CISV2\Modules\Transfers\Stock;

use PDO;

final class ServiceRegistry
{
    private PDO $pdo;
    private ?TransferPackingService $packingService = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function packing(): TransferPackingService
    {
        if ($this->packingService === null) {
            $this->packingService = new TransferPackingService($this->pdo);
        }
        return $this->packingService;
    }
}
