<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace Chamilo\Tests\CourseBundle\Repository;

use Chamilo\CourseBundle\Entity\CDocument;
use Chamilo\Tests\AbstractApiTest;
use Chamilo\Tests\ChamiloTestTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @covers \Chamilo\CourseBundle\Repository\CDocumentRepository
 */
class CDocumentRepositoryTest extends AbstractApiTest
{
    use ChamiloTestTrait;

    public function testGetDocumentsAsAdmin(): void
    {
        $token = $this->getUserToken([]);
        $response = $this->createClientWithCredentials($token)->request('GET', '/api/documents');
        $this->assertResponseIsSuccessful();

        // Asserts that the returned content type is JSON-LD (the default)
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        // Asserts that the returned JSON is a superset of this one
        $this->assertJsonContains([
            '@context' => '/api/contexts/Documents',
            '@id' => '/api/documents',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 0,
        ]);

        $this->assertCount(0, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(CDocument::class);
    }

    public function testCreateFolder(): void
    {
        $course = $this->createCourse('Test');

        // Create folder.
        $resourceLinkList = [
            'cid' => $course->getId(),
            'visibility' => 2,
        ];

        $folderName = 'folder1';
        $token = $this->getUserToken([]);
        $this->createClientWithCredentials($token)->request(
            'POST',
            '/api/documents',
            [
                'json' => [
                    'title' => $folderName,
                    'filetype' => 'folder',
                    'parentResourceNodeId' => $course->getResourceNode()->getId(),
                    'resourceLinkList' => json_encode($resourceLinkList),
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Documents',
            '@type' => 'Documents',
            'title' => $folderName,
        ]);
    }

    public function testUploadFile(): void
    {
        $course = $this->createCourse('Test');

        $resourceLinkList = [
            'cid' => $course->getId(),
            'visibility' => 2,
        ];

        $path = $this->getContainer()->get('kernel')->getProjectDir();

        $filePath = $path.'/public/img/logo.png';
        $fileName = basename($filePath);

        $file = new UploadedFile(
            $filePath,
            $fileName,
            'image/png',
        );

        $token = $this->getUserToken([]);
        $this->createClientWithCredentials($token)->request(
            'POST',
            '/api/documents',
            [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'extra' => [
                    'files' => [
                        'uploadFile' => $file,
                    ],
                ],
                'json' => [
                    'filetype' => 'file',
                    'size' => filesize($filePath),
                    'parentResourceNodeId' => $course->getResourceNode()->getId(),
                    'resourceLinkList' => json_encode($resourceLinkList),
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Documents',
            '@type' => 'Documents',
            'title' => $fileName,
            'filetype' => 'file',
        ]);
    }

    public function testUploadFileInSideASubFolder(): void
    {
        $course = $this->createCourse('Test');

        // Create folder.
        $resourceLinkList = [
            'cid' => $course->getId(),
            'visibility' => 2,
        ];

        $token = $this->getUserToken([]);
        // Creates a folder.
        $folderName = 'myfolder';
        $response = $this->createClientWithCredentials($token)->request(
            'POST',
            '/api/documents',
            [
                'json' => [
                    'title' => $folderName,
                    'filetype' => 'folder',
                    'parentResourceNodeId' => $course->getResourceNode()->getId(),
                    'resourceLinkList' => json_encode($resourceLinkList),
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent());
        $resourceNodeId = $data->resourceNode->id;

        $path = $this->getContainer()->get('kernel')->getProjectDir();

        $filePath = $path.'/public/img/logo.png';
        $fileName = basename($filePath);

        $file = new UploadedFile(
            $filePath,
            $fileName,
            'image/png',
        );

        $token = $this->getUserToken([]);
        $this->createClientWithCredentials($token)->request(
            'POST',
            '/api/documents',
            [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'extra' => [
                    'files' => [
                        'uploadFile' => $file,
                    ],
                ],
                'json' => [
                    'filetype' => 'file',
                    'size' => filesize($filePath),
                    'parentResourceNodeId' => $resourceNodeId,
                    'resourceLinkList' => json_encode($resourceLinkList),
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Documents',
            '@type' => 'Documents',
            'title' => $fileName,
            'filetype' => 'file',
        ]);

        $this->assertMatchesRegularExpression('~'.$folderName.'~', $response->toArray()['resourceNode']['path']);
    }
}
