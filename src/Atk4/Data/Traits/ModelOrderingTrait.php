<?php

namespace Atk4\Symfony\Module\Atk4\Data\Traits;

use Atk4\Data\Model;

/**
 * @method Model       getModel()
 * @method Model\Scope scope()
 * @method mixed       getId()
 */
trait ModelOrderingTrait
{
    protected string $ordering_field = 'ordering';

    public function addOrderingLogic(Model $model, $ordering_field = 'ordering')
    {
        $this->ordering_field = $ordering_field;

        $model->addField($this->ordering_field, [
            'type' => 'float',
            'default' => 1,
            'system' => true,
        ]);

        $model->setOrder($this->ordering_field, 'ASC');

        $model->addUserAction('up', [
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'modifier' => Model\UserAction::MODIFIER_UPDATE,
            'callback' => function ($model) {
                return $model->orderingMoveUp();
            },
        ]);

        $model->addUserAction('down', [
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'modifier' => Model\UserAction::MODIFIER_UPDATE,
            'callback' => function ($model) {
                return $model->orderingMoveDown();
            },
        ]);

        $model->onHook(Model::HOOK_BEFORE_INSERT, function (Model $m, &$data) {
            $count = (int) (clone $m->getModel())->action('count')->getOne();
            $data[$this->ordering_field] = $count + 1;
        });
    }

    public function orderingMoveUp()
    {
        $collection_model = $this->getModel();
        $collection_model->scope()->add($this->scope());

        $ids = [];
        foreach ($collection_model->getIterator() as $k => $item) {
            $ids[] = $item->getId();
            if ($this->getId() === $item->getId() && 1 !== count($ids)) {
                $before = array_pop($ids);
                $after = array_pop($ids);
                $ids[] = $before;
                $ids[] = $after;
            }
        }

        $this->orderingReorder($ids);
    }

    protected function orderingReorder(array $ids)
    {
        $model = $this->getModel();
        foreach (array_values($ids) as $pos => $id) {
            (clone $model)->load($id)->save([$this->ordering_field => $pos + 1]);
        }
    }

    public function orderingMoveDown()
    {
        $collection_model = $this->getModel();
        $collection_model->scope()->add($this->scope());

        $ids = [];
        $last_item = null;
        foreach ($collection_model->getIterator() as $k => $item) {
            $ids[] = $item->getId();
            if (null !== $last_item && $this->getId() === $last_item->getId()) {
                $before = array_pop($ids);
                $after = array_pop($ids);
                $ids[] = $before;
                $ids[] = $after;
            }
            $last_item = clone $item;
        }

        $this->orderingReorder($ids);
    }
}
