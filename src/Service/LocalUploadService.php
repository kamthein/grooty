<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service d'upload local pour MAMP / développement.
 * Les fichiers sont stockés dans public/uploads/events/
 * En production, remplacer par FileUploadService (S3).
 */
class LocalUploadService
{
    private string $uploadDir;
    private string $thumbDir;

    public function __construct(string $projectDir)
    {
        $this->uploadDir = $projectDir . '/public/uploads/events/';
        $this->thumbDir  = $projectDir . '/public/uploads/events/thumbs/';
    }

    public function uploadEventImage(UploadedFile $file): array
    {
        // Créer les dossiers si nécessaire
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbDir)) {
            mkdir($this->thumbDir, 0755, true);
        }

        $ext      = $file->guessExtension() ?? 'jpg';
        $filename = uniqid('img_', true) . '.' . $ext;

        // Déplacer le fichier uploadé
        $file->move($this->uploadDir, $filename);

        // Générer une miniature 200x200
        $thumbName = 'thumb_' . $filename;
        $this->generateThumbnail(
            $this->uploadDir . $filename,
            $this->thumbDir  . $thumbName,
            200
        );

        return [
            'filePath'      => $filename,
            'thumbnailPath' => $thumbName,
        ];
    }

    private function generateThumbnail(string $sourcePath, string $destPath, int $size): void
    {
        if (!extension_loaded('gd')) {
            // Sans GD, copier simplement le fichier
            copy($sourcePath, $destPath);
            return;
        }

        $info = @getimagesize($sourcePath);
        if (!$info) {
            copy($sourcePath, $destPath);
            return;
        }

        [$srcW, $srcH, $type] = $info;

        $src = match($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            IMAGETYPE_GIF  => imagecreatefromgif($sourcePath),
            default        => null,
        };

        if (!$src) {
            copy($sourcePath, $destPath);
            return;
        }

        // Calcul crop carré centré
        $minDim = min($srcW, $srcH);
        $x = (int)(($srcW - $minDim) / 2);
        $y = (int)(($srcH - $minDim) / 2);

        $thumb = imagecreatetruecolor($size, $size);

        // Transparence pour PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $src, 0, 0, $x, $y, $size, $size, $minDim, $minDim);

        match($type) {
            IMAGETYPE_JPEG => imagejpeg($thumb, $destPath, 85),
            IMAGETYPE_PNG  => imagepng($thumb, $destPath),
            IMAGETYPE_WEBP => imagewebp($thumb, $destPath, 85),
            IMAGETYPE_GIF  => imagegif($thumb, $destPath),
            default        => imagejpeg($thumb, $destPath, 85),
        };

        imagedestroy($src);
        imagedestroy($thumb);
    }

    public function delete(string $filename): void
    {
        @unlink($this->uploadDir . $filename);
        @unlink($this->thumbDir  . 'thumb_' . $filename);
    }
}
