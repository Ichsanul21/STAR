<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ConflictReporter;
use Illuminate\Http\Request;

trait ChecksVersion
{
    protected function hasVersionConflict($model, mixed $expected): bool
    {
        if ($expected === null || $expected === '') {
            return false;
        }

        if ((int) $expected !== (int) $model->lock_version) {
            app(ConflictReporter::class)->record($model, $expected);

            return true;
        }

        return false;
    }

    protected function conflictResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'conflict' => true,
                'conflict_id' => session('conflict_id'),
                'errors' => ['conflict' => [$message]],
            ], 409);
        }

        return back()->withErrors(['conflict' => $message]);
    }

    protected function bumpVersion($model): void
    {
        $model->lock_version = (int) $model->lock_version + 1;
    }
}
