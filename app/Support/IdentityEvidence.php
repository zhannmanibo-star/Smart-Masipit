<?php

namespace App\Support;

use App\Models\ResidentApplication;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class IdentityEvidence
{
    public static function rules(): array
    {
        return [
            'id_document' => [
                'bail',
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg',
                'max:4096',
                'dimensions:min_width=320,min_height=200,max_width=1600,max_height=1600',
            ],

            'id_back' => [
                'bail',
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg',
                'max:4096',
                'dimensions:min_width=320,min_height=200,max_width=1600,max_height=1600',
            ],

            'selfie' => [
                'bail',
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg',
                'max:2048',
                'dimensions:min_width=240,min_height=240,max_width=1600,max_height=1600',
            ],

            'identity_consent' => ['accepted'],
        ];
    }

    public static function disk()
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/private/identity-evidence'),
            'visibility' => 'private',
            'throw' => true,
        ]);
    }

    public static function store(array $data): array
    {
        $attributes = [];

        try {
            foreach ([
                'id_document' => 'id_document_path',
                'id_back' => 'id_back_path',
                'selfie' => 'selfie_path',
            ] as $field => $column) {
                $path = (string) Str::uuid() . '.enc';

                $attributes[$column] = $path;

                $bytes = file_get_contents($data[$field]->getRealPath());

                if ($bytes === false) {
                    throw new RuntimeException('Unable to read captured image.');
                }

                self::disk()->put(
                    $path,
                    Crypt::encryptString($bytes)
                );
            }

            $attributes['identity_evidence_version'] = (string) Str::uuid();
            $attributes['identity_submitted_at'] = now();
            $attributes['identity_reviewed_at'] = null;
            $attributes['identity_reviewed_by'] = null;

            return $attributes;
        } catch (Throwable $exception) {
            self::cleanup($attributes);

            throw $exception;
        }
    }

    public static function available(ResidentApplication $application): bool
    {
        if (! $application->hasIdentityEvidence()) {
            return false;
        }

        return self::disk()->exists($application->id_document_path)
            && self::disk()->exists($application->id_back_path)
            && self::disk()->exists($application->selfie_path);
    }

    public static function read(string $path): string
    {
        return Crypt::decryptString(self::disk()->get($path));
    }

    public static function cleanup(array $attributes): void
    {
        foreach ([
            'id_document_path',
            'id_back_path',
            'selfie_path',
        ] as $column) {
            $path = $attributes[$column] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            try {
                self::disk()->delete($path);
            } catch (Throwable $exception) {
                Log::warning('Identity evidence cleanup failed.', [
                    'exception_class' => $exception::class,
                ]);
            }
        }
    }
}