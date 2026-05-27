<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotetakerConfigRequest;
use App\Http\Requests\Settings\UploadAvatarRequest;
use App\Http\Resources\NotetakerConfigResource;
use App\Models\NotetakerConfig;
use App\Services\StorageService;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotetakerConfigController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly StorageService $storage)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $config = $user->notetakerConfig;
        if ($config === null) {
            return $this->error('Notetaker configuration not found.', 404);
        }

        return $this->successResource(new NotetakerConfigResource($config));
    }

    public function update(UpdateNotetakerConfigRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $config = $user->notetakerConfig;
        if ($config === null) {
            return $this->error('Notetaker configuration not found.', 404);
        }

        $config->fill($request->validated());
        $config->save();

        return $this->successResource(new NotetakerConfigResource($config->refresh()));
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        $config = $user->notetakerConfig;
        if ($config === null) {
            return $this->error('Notetaker configuration not found.', 404);
        }

        $file = $request->file('avatar');
        if ($file === null) {
            return $this->error('No avatar file uploaded.', 422);
        }

        $newPath = DB::transaction(function () use ($file, $user, $config): string {
            if (is_string($config->avatar_path) && $config->avatar_path !== '') {
                $this->storage->deleteAvatar($config->avatar_path);
            }

            $path = $this->storage->storeAvatar(is_array($file) ? $file[0] : $file, $user);

            /** @var NotetakerConfig $config */
            $config->avatar_path = $path;
            $config->save();

            return $path;
        });

        return $this->success([
            'avatar_url' => $this->storage->getSignedAvatarUrl($newPath),
        ]);
    }
}
