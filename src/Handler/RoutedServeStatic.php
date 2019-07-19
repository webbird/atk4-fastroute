<?php

declare(strict_types=1);

namespace Abbadon1334\ATKFastRoute\Handler;

use Abbadon1334\ATKFastRoute\Exception\StaticFileExtensionNotAllowed;
use Abbadon1334\ATKFastRoute\Exception\StaticFileNotExists;
use Abbadon1334\ATKFastRoute\Handler\Contracts\iArrayable;
use Abbadon1334\ATKFastRoute\Handler\Contracts\iOnRoute;
use Mimey\MimeTypes;

class RoutedServeStatic implements iOnRoute, iArrayable
{
    /** @var string */
    protected $path;

    /** @var array */
    protected $extensions = [];

    /**
     * RoutedCallable constructor.
     *
     * @param string $path Base path for serving static files
     * @param array $extensions
     */
    public function __construct(string $path, array $extensions)
    {
        $this->path = $path;
        $this->extensions = $extensions;
    }

    /**
     * @param mixed ...$parameters
     *
     * @return mixed
     */
    public function onRoute(...$parameters)
    {
        $request_path = array_shift($parameters);

        // remove query part;
        $request_path = strtok($request_path, '?');

        // get path parts
        $path = pathinfo($request_path, PATHINFO_DIRNAME);
        $file = pathinfo($request_path, PATHINFO_BASENAME);

        $folder_path = $this->getFolderPath($path);

        try {
            $this->isDirAllowed($folder_path);

            $file_path = $folder_path.DIRECTORY_SEPARATOR.$file;
            $this->isFileAllowed($file_path);

            $this->serveFile($file_path);
        } catch (\Throwable $e) {
            http_response_code(403);
        }
    }

    private function getFolderPath(string $path = null)
    {
        return $path === null || $path === '.'
               ? $this->path
               : implode(DIRECTORY_SEPARATOR, [$this->path, $path]);
    }

    private function isDirAllowed($path)
    {
        if ($path !== realpath($path) || ! is_dir($path)) {
            throw new StaticFileExtensionNotAllowed([
                'Requested file folder is not allowed',
                'path' => $path,
                'fullpath' => realpath($path),
            ]);
        }
    }

    private function isFileAllowed($filepath)
    {
        $ext = pathinfo($filepath, PATHINFO_EXTENSION);

        if (! $this->isExtensionAllowed($ext)) {
            throw new StaticFileExtensionNotAllowed([
                'Extension is not allowed',
                'ext' => $ext,
            ]);
        }

        if (! file_exists($filepath)) {
            throw new StaticFileNotExists([
                'Requested File extension not exists',
            ]);
        }
    }

    private function isExtensionAllowed($ext)
    {
        return in_array($ext, $this->extensions);
    }

    private function serveFile(string $file_path)
    {
        http_response_code(200);

        $filename = pathinfo($file_path, PATHINFO_BASENAME);
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);

        $mimeType = (new MimeTypes())->getMimeType($ext);

        header('Content-Type: '.$mimeType.'');
        header('Content-Length: '.filesize($file_path));
        header('Content-Disposition: inline; filename="'.$filename.'"');

        readfile($file_path);
    }

    /**
     * @param array $array
     *
     * @return iOnRoute
     */
    public static function fromArray(array $array): iOnRoute
    {
        return new static(...$array);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [$this->path, $this->extensions];
    }
}
