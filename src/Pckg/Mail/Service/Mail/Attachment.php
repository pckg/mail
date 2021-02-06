<?php

namespace Pckg\Mail\Service\Mail;

use Swift_Attachment;
use Swift_ByteStream_FileByteStream;
use Swift_Mime_Attachment;
use Swift_OutputByteStream;

class Attachment extends Swift_Attachment
{

    protected $path = null;

    /**
     * Create a new Attachment.
     *
     * @param string|Swift_OutputByteStream $data
     * @param string                        $filename
     * @param string                        $contentType
     *
     * @return Swift_Mime_Attachment
     */
    public static function newInstance($data = null, $filename = null, $contentType = null)
    {
        return new self($data, $filename, $contentType);
    }

    /**
     * Create a new Attachment from a filesystem path.
     *
     * @param string $path
     * @param string $contentType optional
     *
     * @return Swift_Mime_Attachment
     */
    public static function fromPath($path, $contentType = null)
    {
        return (self::newInstance()->setFile(
            new Swift_ByteStream_FileByteStream($path),
            $contentType
        ))->setPath($path);
    }

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }
}
