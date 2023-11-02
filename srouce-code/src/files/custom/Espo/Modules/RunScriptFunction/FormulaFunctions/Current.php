<?php

namespace Espo\Modules\RunScriptFunction\FormulaFunctions;

use stdClass;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\ErrorSilent;
use Espo\Core\Formula\Functions\BaseFunction;
use Espo\Core\Formula\ArgumentList;
use Espo\Core\Formula\Manager as FormulaManager;
use Espo\Core\Formula\Func;
use Espo\Core\Formula\Processor;
use Espo\Core\Formula\EvaluatedArgumentList;
use Espo\Core\ORM\Entity;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Core\InjectableFactory;

class Current extends BaseFunction
{
    public static int $nestedCallLevel = 0;

    public function __construct(
        private EntityManager $entityManager,
        // protected Log $log,
        private FormulaManager $formulaManager,
        private InjectableFactory $injectableFactory,
        protected string $name,
        protected Processor $processor,
        private ?Entity $entity = null,
        private ?stdClass $variables = null,
        protected ?Log $log = null
    ) {
        parent::__construct($name, $processor, $entity, $variables, $log);
        if (is_null($log)) {
            throw new Error("formula runScript\\current: logger cannot null");
        }
    }

    /**
     * Chạy formula script đưa vào.
     *
     * @param ArgumentList $arguments
     * @return Entity A result of the function. nếu lỗi trả về null
     * @throws Error
     * @throws ExecutionException
     */
    public function process(ArgumentList $args): ?Entity
    {
        // var_dump($this->variables); // các biến trong script function này được gọi
        // die;
        $this->log->debug("formula runScript\\current: nestedCallLevel: " . self::$nestedCallLevel);
        self::$nestedCallLevel++;

        if (self::$nestedCallLevel > 1) {
            $this->log->error("formula runScript\\current không được gọi lặp lại. Check lại các script.");
            self::$nestedCallLevel--;
            return null;
        }

        if ($args->count() < 1) {
            $this->log->error("formula runScript\\current cần tối thiểu 1 tham số: SCRIPT");
            self::$nestedCallLevel--;
            return null;
        }

        $customScript = $this->evaluate($args->offsetGet(0));
        $options =  $this->evaluate($args->offsetGet(1));

        $entity = $this->entity;
        if ($entity == null) {
            $this->log->error("formula runScript\\current: entity cannot null");
            self::$nestedCallLevel--;
            return null;
        }

        if (!$customScript || empty($customScript)) {
            $customScript = "";
        }


        if (!is_null($options) && !is_object($options)) {
            $this->log->error("formula runScript\\current: biến đầu vào không hợp lệ. Cần truyền vào object.");
            self::$nestedCallLevel--;
            return null;
        }

        $varObj = (object) [];

        if (property_exists($options, 'varObj') && is_object($options->varObj)) {
            $varObj = $options->varObj;
        }

        $entityType = $entity->getEntityType();
        $entityId = $entity->getId();
        $this->log->debug("formula runScript\\current: run formula with target $entityType: $entityId");
        $this->log->debug("formula runScript\\current: formula: $customScript");
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
            $this->log->error("formula runScript\\current: formula script of $entityType($id) failed: " . $e->getMessage());
        }
    }
}
