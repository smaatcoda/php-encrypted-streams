<?php
namespace Jsq\EncryptionStreams;

use InvalidArgumentException;
use LogicException;

class CtrIv implements InitializationVector
{
    const BLOCK_SIZE = 16;
    const CTR_BLOCK_MAX = 65536; // maximum 16-bit unsigned integer value

    /**
     * The hash initialization vector, stored as eight 16-bit words
     * @var int[]
     */
    private $iv;

    /**
     * The counter offset to add to the initialization vector
     * @var int[]
     */
    private $ctrOffset;

    public function __construct($iv)
    {
        if (strlen($iv) !== openssl_cipher_iv_length('aes-128-ctr')) {
            throw new InvalidArgumentException('Invalid initialization veector'
                . ' provided to ' . static::class);
        }

        $this->iv = $this->extractIvParts($iv);
        $this->resetOffset();
    }

    public function getCipherMethod()
    {
        return 'CTR';
    }

    public function getCurrentIv()
    {
        return $this->calculateCurrentIv($this->iv, $this->ctrOffset);
    }

    public function requiresPadding()
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        if ($offset % self::BLOCK_SIZE !== 0) {
            throw new LogicException('CTR initialization vectors only support '
                . ' seeking to indexes that are multiples of '
                . self::BLOCK_SIZE);
        }

        if ($whence === SEEK_SET) {
            $this->resetOffset();
            $this->incrementOffset($offset / self::BLOCK_SIZE);
        } elseif ($whence === SEEK_CUR) {
            if ($offset < 0) {
                throw new LogicException('Negative offsets are not supported.');
            }

            $this->incrementOffset($offset / self::BLOCK_SIZE);
        } else {
            throw new LogicException('Unrecognized whence.');
        }
    }

    public function supportsArbitrarySeeking()
    {
        return true;
    }

    public function update($cipherTextBlock)
    {
        $this->incrementOffset(strlen($cipherTextBlock) / self::BLOCK_SIZE);
    }

    /**
     * @param string $iv
     * @return int[]
     */
    private function extractIvParts($iv)
    {
        return array_map(function ($part) {
            return unpack('nnum', $part)['num'];
        }, str_split($iv, 2));
    }

    /**
     * @param int[] $baseIv
     * @param int[] $ctrOffset
     * @return string
     */
    private function calculateCurrentIv(array $baseIv, array $ctrOffset)
    {
        $iv = array_fill(0, 8, 0);
        $carry = 0;
        for ($i = 7; $i >= 0; $i--) {
            $sum = $ctrOffset[$i] + $baseIv[$i] + $carry;
            $carry = (int) ($sum / self::CTR_BLOCK_MAX);
            $iv[$i] = $sum % self::CTR_BLOCK_MAX;
        }

        return implode(array_map(function ($ivBlock) {
            return pack('n', $ivBlock);
        }, $iv));
    }

    /**
     * @param int $incrementBy
     */
    private function incrementOffset($incrementBy)
    {
        for ($i = 7; $i >= 0; $i--) {
            $incrementedBlock = $this->ctrOffset[$i] + $incrementBy;
            $incrementBy = (int) ($incrementedBlock / self::CTR_BLOCK_MAX);
            $this->ctrOffset[$i] = $incrementedBlock % self::CTR_BLOCK_MAX;
        }
    }

    private function resetOffset()
    {
        $this->ctrOffset = array_fill(0, 8, 0);
    }
}