<?php

namespace Espo\Modules\RunScriptFunction\FormulaFunctions;

use stdClass;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\ErrorSilent;
use Espo\Core\Formula\Functions\BaseFunction;
use Espo\Core\Formula\ArgumentList;
use Espo\Core\Formula\Manager as FormulaManager;
use Espo\Core\Formula\Func;
use Espo\Core\Formula\EvaluatedArgumentList;
use Espo\Core\ORM\Entity;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;

class Current implements Func
{
    public static int $nestedCallLevel = 0;

    public function __construct(private EntityManager $entityManager, private Log $log, private FormulaManager $formulaManager)
    {
    }
    /**
     * Tính toán giá trị ưu đãi đóng theo kỳ và đóng theo năm.
     * @param EvaluatedArgumentList $arguments
     * @throws Error
     * @return array là số tiền đóng theo kỳ và theo năm tính được, có lỗi lúc xử lý thì trả về []
     */
    public function process(EvaluatedArgumentList $arguments): array
    {

        $this->log->info("formula salesOrder\\calculateUuDaiItem: nestedCallLevel: " . self::$nestedCallLevel);
        self::$nestedCallLevel++;

        if (self::$nestedCallLevel > 1) {
            $this->log->error("formula salesOrder\\calculateUuDaiItem không được gọi lặp lại. Check lại các script.");
            self::$nestedCallLevel--;
            return [];
        }

        if ($arguments->count() != 1) {
            $this->log->error("formula salesOrder\\calculateUuDaiItem cần 1 tham số: id của SaleOrderUuDaiItem cần tính");
            self::$nestedCallLevel--;
            return [];
        }
        $idUuDaiItem = $arguments->offsetGet(0);

        if($idUuDaiItem == null || $idUuDaiItem == "") {
            $this->log->error("formula salesOrder\\calculateUuDaiItem: id SaleOrderUuDaiItem không hợp lệ:" . $idUuDaiItem);
            self::$nestedCallLevel--;
            return [];
        }

        $entitySaleOrderUuDaiItem = $this->entityManager->getEntityById("SaleOrderUuDaiItem", $idUuDaiItem);
        if ($entitySaleOrderUuDaiItem == null) {
            $this->log->error("formula salesOrder\\calculateUuDaiItem: không tìm thấy SaleOrderUuDaiItem với Id: $idUuDaiItem");
            self::$nestedCallLevel--;
            return [];
        }

        $customScript = $entitySaleOrderUuDaiItem->get('formula');

        if (!$customScript || empty($customScript)) {
            $customScript = "";
        }

        $this->log->debug("formula salesOrder\\calculateUuDaiItem: run formula of SaleOrderUuDaiItemId: $idUuDaiItem");
        $this->log->debug("formula salesOrder\\calculateUuDaiItem: formula: $customScript");
        $this->runScript($customScript, $entitySaleOrderUuDaiItem, (object) [
            // 'i' => 500,
        ]);

        //khi SKIP_ALL sẽ không lưu được các trường link multiple
        $this->entityManager->saveEntity($entitySaleOrderUuDaiItem, [SaveOption::SILENT => true]);

        $result = [
            $entitySaleOrderUuDaiItem->get("soTien"),
            $entitySaleOrderUuDaiItem->get("soTienCaNam"),
        ];
        self::$nestedCallLevel--;
        return $result;
    }


    /**
     * @param string $script
     * @param Entity $entity
     * @param stdClass $variables các biến được truyền vào script. Ví dụ: (object) ['i' => 500] thì trong script có thể dùng biến $i
     */
    private function runScript(string $script, Entity $entity, stdClass $variables): void
    {
        try {
            $this->formulaManager->run($script, $entity, $variables);
        } catch (Error $e) {
            $entityType = $entity->getEntityType();
            $id = $entity->getId();
            $this->log->error("formula salesOrder\\calculateUuDaiItem: formula script of $entityType($id) failed: " . $e->getMessage());
        }
    }
}
