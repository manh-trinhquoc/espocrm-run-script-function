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

class Target implements Func
{
    public static int $nestedCallLevel = 0;

    public function __construct(private EntityManager $entityManager, private Log $log, private FormulaManager $formulaManager)
    {
    }

    /**
     * Chạy formula script đưa vào
     * @param EvaluatedArgumentList $arguments
     * @throws Error
     * @return Entity. nếu lỗi trả về null
     */
    public function process(EvaluatedArgumentList $arguments): ?Entity
    {

        $this->log->debug("formula runScript\\target: nestedCallLevel: " . self::$nestedCallLevel);
        self::$nestedCallLevel++;

        if (self::$nestedCallLevel > 1) {
            $this->log->error("formula runScript\\target không được gọi lặp lại. Check lại các script.");
            self::$nestedCallLevel--;
            return null;
        }

        if ($arguments->count() < 3) {
            $this->log->error("formula runScript\\target cần tối thiểu 3 tham số: ENTITY_TYPE, ID, SCRIPT");
            self::$nestedCallLevel--;
            return null;
        }
        $entityType = $arguments->offsetGet(0);
        $entityId = $arguments->offsetGet(1);
        $customScript = $arguments->offsetGet(2);
        $options = $arguments->offsetGet(3);

        if($entityType == null || $entityType == "") {
            $this->log->error("formula runScript\\target: entityType không hợp lệ:" . $entityType);
            self::$nestedCallLevel--;
            return null;
        }

        if($entityId == null || $entityId == "") {
            $this->log->error("formula runScript\\target: id không hợp lệ:" . $entityId);
            self::$nestedCallLevel--;
            return null;
        }

        $entity = $this->entityManager->getEntityById($entityType, $entityId);
        if ($entity == null) {
            $this->log->error("formula runScript\\target: không tìm thấy entity: $entityType với Id: $entityId");
            self::$nestedCallLevel--;
            return null;
        }

        if (!$customScript || empty($customScript)) {
            $customScript = "";
        }


        if (!is_null($options) && !is_object($options)) {
            $this->log->error("formula runScript\\target: biến đầu vào không hợp lệ. Cần truyền vào object.");
            self::$nestedCallLevel--;
            return null;
        }

        $varObj = (object) [];

        if (property_exists($options, 'varObj') && is_object($options->varObj)) {
            $varObj = $options->varObj;
        }

        $this->log->debug("formula runScript\\target: run formula with target $entityType: $entityId");
        $this->log->debug("formula runScript\\target: formula: $customScript");
        $this->runScript($customScript, $entity, $varObj);

        if (property_exists($options, 'save')) {
            $save = $options->save;

            $saveOptions = [];
            if ($save == 'SILENT') {
                $saveOptions[SaveOption::SILENT] = true; //workflows will be ignored, modified fields won't be change;
            }
            if ($save == 'SKIP_ALL') {
                $saveOptions[SaveOption::SKIP_ALL] = true; //khi SKIP_ALL sẽ không lưu được các trường link multiple
            }

            $this->entityManager->saveEntity($entity, $saveOptions);
        }

        self::$nestedCallLevel--;
        return $entity;
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
            $this->log->error("formula runScript\\target: formula script of $entityType($id) failed: " . $e->getMessage());
        }
    }
}
