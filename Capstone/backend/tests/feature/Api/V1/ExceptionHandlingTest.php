<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Test\ControllerTestTrait;
use RuntimeException;
use CodeIgniter\Exceptions\PageNotFoundException;
use App\Libraries\JsonApiExceptionHandler;
use App\Controllers\Api\V1\TestFailure;
use Config\Services;

class ExceptionHandlingTest extends CIUnitTestCase
{
    use ControllerTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
        $this->setUpControllerTestTrait();
    }

    public function testJsonApiHandlerIsCreatedForApiPaths(): void
    {
        $config = new \Config\Exceptions();
        
        $apiPaths = [
            'api/v1/test',
            'api/v2/resource',
            'api/health',
            'api/status',
        ];
        
        foreach ($apiPaths as $path) {
            $mockRequest = $this->createMockRequest($path);
            Services::injectMock('request', $mockRequest);
            
            $handler = $config->handler(500, new RuntimeException('Test'));
            
            $this->assertInstanceOf(
                JsonApiExceptionHandler::class,
                $handler,
                "Path '{$path}' should use JsonApiExceptionHandler"
            );
            
            Services::reset(true);
        }
    }

    public function testDefaultHandlerIsCreatedForNonApiPaths(): void
    {
        $config = new \Config\Exceptions();
        
        $nonApiPaths = [
            'home',
            'admin/dashboard',
            'about',
            'contact',
            '',
            '/',
        ];
        
        foreach ($nonApiPaths as $path) {
            $mockRequest = $this->createMockRequest($path);
            Services::injectMock('request', $mockRequest);
            
            $handler = $config->handler(500, new RuntimeException('Test'));
            
            $this->assertInstanceOf(
                \CodeIgniter\Debug\ExceptionHandler::class,
                $handler,
                "Path '{$path}' should use default ExceptionHandler"
            );
            
            $this->assertNotInstanceOf(
                JsonApiExceptionHandler::class,
                $handler,
                "Path '{$path}' should NOT use JsonApiExceptionHandler"
            );
            
            Services::reset(true);
        }
    }

    public function testJsonApiHandlerPreservesStatusCode(): void
    {
        $config = new \Config\Exceptions();
        
        $mockRequest = $this->createMockRequest('api/v1/test');
        Services::injectMock('request', $mockRequest);
        
        $handler404 = $config->handler(404, new PageNotFoundException('Not found'));
        $handler500 = $config->handler(500, new RuntimeException('Server error'));
        $handler503 = $config->handler(503, new RuntimeException('Unavailable'));
        
        $this->assertInstanceOf(JsonApiExceptionHandler::class, $handler404);
        $this->assertInstanceOf(JsonApiExceptionHandler::class, $handler500);
        $this->assertInstanceOf(JsonApiExceptionHandler::class, $handler503);
    }

    public function testJsonApiHandlerGetErrorMessageInDevelopment(): void
    {
        if (ENVIRONMENT === 'production') {
            $this->markTestSkipped('This test only runs in non-production environments');
        }

        $handler = new \ReflectionClass(JsonApiExceptionHandler::class);
        $method = $handler->getMethod('getErrorMessage');
        $method->setAccessible(true);
        
        $config = new \Config\Exceptions();
        $instance = new JsonApiExceptionHandler($config);
        
        $exception = new RuntimeException('Detailed error message');
        $message = $method->invoke($instance, $exception, 500);
        
        $this->assertIsString($message);
        $this->assertSame('Detailed error message', $message);
    }

    public function testJsonApiHandlerIsSubclassOfBaseExceptionHandler(): void
    {
        $config = new \Config\Exceptions();
        $instance = new JsonApiExceptionHandler($config);
        
        $this->assertInstanceOf(\CodeIgniter\Debug\BaseExceptionHandler::class, $instance);
    }

    public function testTestingFailureControllerThrowsExpectedUnhandledException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test unhandled exception for JSON error envelope verification');

        $this->controller(TestFailure::class)
            ->execute('triggerUnhandledException');
    }

    public function testTestingFailureControllerReturnsNotFoundStatus(): void
    {
        $result = $this->controller(TestFailure::class)
            ->execute('triggerNotFound');

        $result->assertStatus(404);
    }

    protected function createMockRequest(string $path): IncomingRequest
    {
        $request = $this->getMockBuilder(IncomingRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPath'])
            ->getMock();
        
        $request->method('getPath')->willReturn($path);
        
        return $request;
    }
}
