<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class UploadService extends Service
{
    protected function tableExists($tableName)
    {
        $databaseConfig = $this->app->databaseConfig();
        $databaseName = is_array($databaseConfig) ? (string) ($databaseConfig['database'] ?? '') : '';

        if ($databaseName === '') {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = :table_schema
               AND table_name = :table_name
             LIMIT 1',
            array(
                'table_schema' => $databaseName,
                'table_name' => (string) $tableName,
            )
        );

        return $row !== null;
    }

    public function hasUploadedFile($fieldName)
    {
        return isset($_FILES[$fieldName])
            && is_array($_FILES[$fieldName])
            && (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function businessTypeOptions()
    {
        return array(
            'general' => '通用附件',
            'banner' => '首页轮播',
            'post' => '帖子封面',
            'material' => '资料图片',
            'forecast' => '预测封面',
            'avatar' => '头像图片',
            'site' => '站点资源',
        );
    }

    public function listUploads(array $filters = array())
    {
        if (!$this->tableExists('uploads')) {
            return array();
        }

        $sql = 'SELECT * FROM uploads WHERE status = 1';
        $params = array();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $businessType = trim((string) ($filters['business_type'] ?? ''));

        if ($keyword !== '') {
            $sql .= ' AND (file_name LIKE :keyword OR file_path LIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        }

        if ($businessType !== '' && isset($this->businessTypeOptions()[$businessType])) {
            $sql .= ' AND business_type = :business_type';
            $params['business_type'] = $businessType;
        }

        $sql .= ' ORDER BY id DESC LIMIT 120';

        return $this->db()->fetchAll($sql, $params);
    }

    public function saveUploadedFile($fieldName, $businessType, array $actor, array $options = array())
    {
        if (!$this->tableExists('uploads')) {
            throw new RuntimeException('当前数据库还没有附件上传表，请先刷新后台完成补表。');
        }

        if (!$this->hasUploadedFile($fieldName)) {
            throw new RuntimeException('请选择要上传的文件。');
        }

        $businessType = trim((string) $businessType);
        if (!isset($this->businessTypeOptions()[$businessType])) {
            throw new RuntimeException('上传业务类型无效。');
        }

        $file = $_FILES[$fieldName];
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($errorCode));
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = array("jpg", "jpeg", "png", "gif", "webp", "bmp");
        $allowedMimeTypes = array(
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",
            "image/bmp",
            "image/x-ms-bmp",
        );
        $mimeExtensionMap = array(
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/gif" => "gif",
            "image/webp" => "webp",
            "image/bmp" => "bmp",
            "image/x-ms-bmp" => "bmp",
        );
        $imageInfo = null;

        if ($originalName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('上传文件无效，请重新选择文件。');
        }

        $maxSize = isset($options['max_size']) ? (int) $options['max_size'] : (5 * 1024 * 1024);
        if ($fileSize <= 0 || $fileSize > $maxSize) {
            throw new RuntimeException('上传文件大小不能超过 5MB。');
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            if (empty($options["infer_image_extension"])) {
                throw new RuntimeException("只允许上传 jpg、jpeg、png、gif、webp 图片。");
            }

            $imageInfo = @getimagesize($tmpName);
            $inferredMimeType = is_array($imageInfo) && !empty($imageInfo["mime"]) ? (string) $imageInfo["mime"] : "";
            if (!isset($mimeExtensionMap[$inferredMimeType]) || !in_array($inferredMimeType, $allowedMimeTypes, true)) {
                throw new RuntimeException("只允许上传 jpg、jpeg、png、gif、webp 图片。");
            }

            $extension = $mimeExtensionMap[$inferredMimeType];
        }

        if ($imageInfo === null) {
            $imageInfo = @getimagesize($tmpName);
        }
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new RuntimeException('上传文件不是有效图片。');
        }

        $mimeType = (string) $imageInfo['mime'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('图片 MIME 类型无效，禁止上传。');
        }

        $subDirectory = date('Ym');
        $relativeDirectory = 'uploads/' . $businessType . '/' . $subDirectory;
        $absoluteDirectory = $this->app->basePath('public/' . $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0777, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('上传目录创建失败，请检查目录权限。');
        }

        $storageName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('上传文件保存失败，请重试。');
        }

        if ($this->shouldOptimizeUploadedImage($options)) {
            $this->optimizeUploadedImage($absolutePath, $extension, $imageInfo, $options);
            $optimizedInfo = @getimagesize($absolutePath);
            if (is_array($optimizedInfo) && !empty($optimizedInfo['mime'])) {
                $imageInfo = $optimizedInfo;
                $mimeType = (string) $optimizedInfo['mime'];
            }
            clearstatcache(true, $absolutePath);
            $optimizedSize = filesize($absolutePath);
            if ($optimizedSize !== false) {
                $fileSize = (int) $optimizedSize;
            }
        }

        $publicPath = \public_url($relativeDirectory . '/' . $storageName);
        $uploadId = (int) $this->db()->insertGetId(
            'INSERT INTO uploads (business_type, related_id, file_name, storage_name, file_path, file_ext, mime_type, file_size, image_width, image_height, sha1_hash, uploaded_by_type, uploaded_by_id, status, created_at, updated_at)
             VALUES (:business_type, :related_id, :file_name, :storage_name, :file_path, :file_ext, :mime_type, :file_size, :image_width, :image_height, :sha1_hash, :uploaded_by_type, :uploaded_by_id, :status, :created_at, :updated_at)',
            array(
                'business_type' => $businessType,
                'related_id' => isset($options['related_id']) ? (int) $options['related_id'] : 0,
                'file_name' => $originalName,
                'storage_name' => $storageName,
                'file_path' => $publicPath,
                'file_ext' => $extension,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'image_width' => (int) ($imageInfo[0] ?? 0),
                'image_height' => (int) ($imageInfo[1] ?? 0),
                'sha1_hash' => sha1_file($absolutePath) ?: '',
                'uploaded_by_type' => 'admin',
                'uploaded_by_id' => (int) ($actor['id'] ?? 0),
                'status' => 1,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            )
        );

        return $this->db()->fetch('SELECT * FROM uploads WHERE id = :id LIMIT 1', array('id' => $uploadId));
    }

    protected function shouldOptimizeUploadedImage(array $options)
    {
        if (array_key_exists('optimize_image', $options)) {
            return (bool) $options['optimize_image'];
        }

        return true;
    }

    protected function optimizeUploadedImage($absolutePath, $extension, array $imageInfo, array $options)
    {
        if (!extension_loaded('gd') || !is_file($absolutePath)) {
            return false;
        }

        $extension = strtolower((string) $extension);
        if ($extension === 'gif') {
            return false;
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $fileSize = filesize($absolutePath);
        $fileSize = $fileSize === false ? 0 : (int) $fileSize;
        $minSize = isset($options['optimize_min_size']) ? max(0, (int) $options['optimize_min_size']) : (300 * 1024);
        $maxWidth = isset($options['max_image_width']) ? max(320, (int) $options['max_image_width']) : 1920;
        $maxHeight = isset($options['max_image_height']) ? max(320, (int) $options['max_image_height']) : 1920;
        $quality = isset($options['image_quality']) ? (int) $options['image_quality'] : 82;
        $quality = max(55, min(92, $quality));
        $needsResize = $width > $maxWidth || $height > $maxHeight;

        if (!$needsResize && $fileSize > 0 && $fileSize < $minSize) {
            return false;
        }

        $createFunction = null;
        $saveFunction = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $createFunction = function_exists('imagecreatefromjpeg') ? 'imagecreatefromjpeg' : null;
                $saveFunction = 'jpeg';
                break;
            case 'png':
                $createFunction = function_exists('imagecreatefrompng') ? 'imagecreatefrompng' : null;
                $saveFunction = 'png';
                break;
            case 'webp':
                $createFunction = function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null;
                $saveFunction = function_exists('imagewebp') ? 'webp' : null;
                break;
            case 'bmp':
                $createFunction = function_exists('imagecreatefrombmp') ? 'imagecreatefrombmp' : null;
                $saveFunction = function_exists('imagebmp') ? 'bmp' : null;
                break;
            default:
                return false;
        }

        if ($createFunction === null || $saveFunction === null) {
            return false;
        }

        $source = @$createFunction($absolutePath);
        if (!$source) {
            return false;
        }

        $target = $source;
        if ($needsResize) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $targetWidth = max(1, (int) floor($width * $ratio));
            $targetHeight = max(1, (int) floor($height * $ratio));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$target) {
                imagedestroy($source);
                return false;
            }

            if ($saveFunction === 'png' || $saveFunction === 'webp') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                if ($transparent !== false) {
                    imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
                }
            }

            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        }

        $saved = false;
        if ($saveFunction === 'jpeg') {
            $saved = imagejpeg($target, $absolutePath, $quality);
        } elseif ($saveFunction === 'png') {
            $pngCompression = isset($options['png_compression']) ? (int) $options['png_compression'] : 7;
            $saved = imagepng($target, $absolutePath, max(0, min(9, $pngCompression)));
        } elseif ($saveFunction === 'webp') {
            $saved = imagewebp($target, $absolutePath, $quality);
        } elseif ($saveFunction === 'bmp') {
            $saved = imagebmp($target, $absolutePath);
        }

        if ($target !== $source) {
            imagedestroy($target);
        }
        imagedestroy($source);

        if ($saved) {
            clearstatcache(true, $absolutePath);
        }

        return (bool) $saved;
    }

    protected function uploadErrorMessage($errorCode)
    {
        switch ((int) $errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return '上传文件超过服务器限制。';
            case UPLOAD_ERR_PARTIAL:
                return '上传文件不完整，请重新上传。';
            case UPLOAD_ERR_NO_FILE:
                return '请选择要上传的文件。';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '服务器缺少临时目录，无法上传。';
            case UPLOAD_ERR_CANT_WRITE:
                return '服务器无法写入上传文件。';
            case UPLOAD_ERR_EXTENSION:
                return '上传被服务器扩展中止。';
            default:
                return '上传失败，请稍后重试。';
        }
    }
}
