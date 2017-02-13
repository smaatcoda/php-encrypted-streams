<?php
namespace Jsq\EncryptionStreams;

use GuzzleHttp\Psr7;
use Psr\Http\Message\StreamInterface;

class AesEncryptingStreamTest extends \PHPUnit_Framework_TestCase
{

    const KB = 1024;
    const MB = 1048576;

    use AesEncryptionStreamTestTrait;

    /**
     * @dataProvider cartesianJoinIvKeySizeProvider
     *
     * @param InitializationVector $iv
     * @param int $keySize
     */
    public function testMemoryUsageRemainsConstant(
        InitializationVector $iv,
        $keySize
    ) {
        $memory = memory_get_usage();

        $stream = new AesDecryptingStream(
            new RandomByteStream(124 * self::MB),
            'foo',
            $iv,
            $keySize
        );

        while (!$stream->eof()) {
            $stream->read(self::MB);
        }

        $this->assertLessThanOrEqual($memory + self::MB, memory_get_usage());
    }

    /**
     * @dataProvider cartesianJoinInputIvKeySizeProvider
     *
     * @param StreamInterface $plainText
     * @param InitializationVector $iv
     * @param int $keySize
     */
    public function testStreamOutputSameAsOpenSSL(
        StreamInterface $plainText,
        InitializationVector $iv,
        $keySize
    ) {
        $key = 'foo';

        $this->assertSame(
            (string) new AesEncryptingStream($plainText, $key, $iv, $keySize),
            openssl_encrypt(
                (string) $plainText,
                "AES-{$keySize}-{$iv->getCipherMethod()}",
                $key,
                OPENSSL_RAW_DATA,
                $iv->getCurrentIv()
            )
        );
    }
}
