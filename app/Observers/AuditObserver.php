<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditObserver
{
    public function __construct(private ActivityLogger $logger)
    {
    }

    public function created(Model $model): void
    {
        $this->write('created', $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $original = $model->getOriginal();
        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $original[$key] ?? null;
        }

        $this->write('updated', $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write('deleted', $model, $model->getOriginal(), []);
    }

    private function write(string $event, Model $model, array $old, array $new): void
    {
        if (! config('audit.enabled', true)) {
            return;
        }

        $name = Str::snake(class_basename($model));
        $label = class_basename($model).' #'.$model->getKey();

        $this->logger->log(
            $name.'.'.$event,
            ucfirst($event).' '.$label,
            $model instanceof \App\Models\Event ? $model : null,
            $model,
            [],
            $old,
            $new
        );
    }
}
