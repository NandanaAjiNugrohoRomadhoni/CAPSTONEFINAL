<?php

namespace Tests\Feature\Api\V1;

use Config\OpenApiDocs;
use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class DocsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private string $cachePath;
    private bool $originalCacheExists = false;
    private string $originalCacheContents = '';
    private ?int $originalCacheMtime = null;

    /**
     * @var array<string, int>
     */
    private array $sourceFileMtimes = [];

    protected function setUp(): void
    {
        parent::setUp();

        Services::reset(true);

        $this->cachePath = config(OpenApiDocs::class)->cachePath;

        if (is_file($this->cachePath)) {
            $this->originalCacheExists    = true;
            $this->originalCacheContents  = (string) file_get_contents($this->cachePath);
            $this->originalCacheMtime     = filemtime($this->cachePath) ?: null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->sourceFileMtimes as $file => $mtime) {
            touch($file, $mtime);
        }

        if ($this->originalCacheExists) {
            file_put_contents($this->cachePath, $this->originalCacheContents, LOCK_EX);

            if ($this->originalCacheMtime !== null) {
                touch($this->cachePath, $this->originalCacheMtime);
            }
        } elseif (is_file($this->cachePath)) {
            unlink($this->cachePath);
        }

        Services::reset(true);

        parent::tearDown();
    }

    public function testDocsIndexIsPublicAndBootstrapsScalarAgainstSpecEndpoint(): void
    {
        $result = $this->get('api/docs');

        $result->assertStatus(200);

        $body = $result->response()->getBody();

        $this->assertStringContainsString('<div id="app"></div>', $body);
        $this->assertStringContainsString('https://cdn.jsdelivr.net/npm/@scalar/api-reference', $body);
        $this->assertStringContainsString('Scalar.createApiReference', $body);
        $this->assertStringContainsString("new URL('spec', docsBaseUrl).toString()", $body);
        $this->assertStringNotContainsString("site_url('api/docs/spec')", $body);
    }

    public function testDocsSpecIsPublicJsonObjectWithRequiredTopLevelSectionsAndSlicePaths(): void
    {
        $result = $this->get('api/docs/spec');

        $result->assertStatus(200);

        $response = $result->response();
        $body     = $response->getBody();

        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $decodedObject = json_decode($body);
        $this->assertInstanceOf(\stdClass::class, $decodedObject);

        $json = json_decode($body, true);
        $this->assertIsArray($json);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), json_last_error_msg());

        $this->assertOpenApiContract($json);
        $this->assertSpecExamplesAreSanitized($body);
    }

    public function testDocsSpecServesExistingValidCacheWithoutRegeneration(): void
    {
        $cachedJson = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Cached Test Spec', 'version' => 'test'],
            'paths' => ['/cached-only' => ['get' => ['summary' => 'cached']]],
            'components' => ['securitySchemes' => []],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->writeCache($cachedJson, time() + 30);

        $result = $this->get('api/docs/spec');

        $result->assertStatus(200);
        $this->assertJsonStringEqualsJsonString($cachedJson, $result->response()->getBody());
    }

    public function testDocsSpecRegeneratesCacheWhenMissing(): void
    {
        if (is_file($this->cachePath)) {
            unlink($this->cachePath);
        }

        Services::reset(true);

        $result = $this->get('api/docs/spec');

        $result->assertStatus(200);
        $this->assertFileExists($this->cachePath);

        $json = json_decode($result->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertOpenApiContract($json);
        $this->assertJsonStringEqualsJsonString(
            (string) file_get_contents($this->cachePath),
            $result->response()->getBody(),
        );
    }

    public function testDocsSpecRegeneratesCacheWhenStale(): void
    {
        $primed = Services::openApiSpec(false)->getSpecJson(true);
        $this->assertSame('generated', $primed['source']);

        $sourceFile = APPPATH . 'OpenApi/OpenApiSpec.php';
        $this->rememberSourceFileMtime($sourceFile);

        $staleCacheTime = time() - 20;
        $sourceTime     = time() - 10;

        touch($this->cachePath, $staleCacheTime);
        touch($sourceFile, $sourceTime);
        clearstatcache(true, $this->cachePath);
        clearstatcache(true, $sourceFile);

        Services::reset(true);

        $result = $this->get('api/docs/spec');

        $result->assertStatus(200);
        $this->assertGreaterThan($staleCacheTime, (int) filemtime($this->cachePath));
        $this->assertJsonStringEqualsJsonString(
            (string) file_get_contents($this->cachePath),
            $result->response()->getBody(),
        );

        $json = json_decode($result->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertOpenApiContract($json);
        $this->assertSame('3.1.0', $json['openapi']);
    }

    public function testDocsSpecRegeneratesCacheWhenOpenApiDocsConfigChanges(): void
    {
        $primed = Services::openApiSpec(false)->getSpecJson(true);
        $this->assertSame('generated', $primed['source']);

        $configFile = APPPATH . 'Config/OpenApiDocs.php';
        $this->rememberSourceFileMtime($configFile);

        $staleCacheTime = time() - 20;
        $configTime     = time() - 10;

        touch($this->cachePath, $staleCacheTime);
        touch($configFile, $configTime);
        clearstatcache(true, $this->cachePath);
        clearstatcache(true, $configFile);

        Services::reset(true);

        $result = $this->get('api/docs/spec');

        $result->assertStatus(200);
        $this->assertGreaterThan($staleCacheTime, (int) filemtime($this->cachePath));
        $this->assertJsonStringEqualsJsonString(
            (string) file_get_contents($this->cachePath),
            $result->response()->getBody(),
        );

        $json = json_decode($result->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertOpenApiContract($json);
    }

    public function testPluralDocsSpecAliasServesSameOpenApiDocument(): void
    {
        $canonical = $this->get('api/docs/spec');
        $alias     = $this->get('api/docs/specs');

        $canonical->assertStatus(200);
        $alias->assertStatus(200);

        $this->assertStringContainsString(
            'application/json',
            $alias->response()->getHeaderLine('Content-Type'),
        );

        $this->assertJsonStringEqualsJsonString(
            $canonical->response()->getBody(),
            $alias->response()->getBody(),
        );
    }

    public function testSpkBasahOperationalPreviewSpecDocumentsGudangAccess(): void
    {
        $result = $this->get('api/docs/spec');

        $result->assertStatus(200);

        $json = json_decode($result->response()->getBody(), true);
        $this->assertIsArray($json);

        $operation = $json['paths']['/api/v1/spk/basah/operational-stock-preview']['post'] ?? null;
        $this->assertIsArray($operation);

        $description = $operation['description'] ?? '';
        $forbiddenDescription = $operation['responses']['403']['description'] ?? '';

        $this->assertStringContainsString('Accessible to admin, dapur, and gudang users.', $description);
        $this->assertStringContainsString('admin, dapur, or gudang role', $forbiddenDescription);
    }

    /**
     * @param array<string, mixed> $json
     */
    private function assertOpenApiContract(array $json): void
    {
        $this->assertArrayHasKey('openapi', $json);
        $this->assertArrayHasKey('info', $json);
        $this->assertArrayHasKey('components', $json);
        $this->assertArrayHasKey('securitySchemes', $json['components']);
        $this->assertArrayHasKey('bearerAuth', $json['components']['securitySchemes']);

        $this->assertArrayHasKey('paths', $json);
        $this->assertArrayHasKey('/api/v1/auth/login', $json['paths']);
        $this->assertArrayHasKey('/api/v1/auth/me', $json['paths']);
        $this->assertArrayHasKey('/api/v1/items', $json['paths']);
        $this->assertArrayHasKey('/api/v1/item-categories', $json['paths']);
        $this->assertArrayHasKey('/api/v1/transaction-types', $json['paths']);
        $this->assertArrayHasKey('/api/v1/approval-statuses', $json['paths']);
        $this->assertArrayHasKey('/api/v1/meal-times', $json['paths']);
        $this->assertArrayHasKey('/api/v1/item-units', $json['paths']);
        $this->assertArrayHasKey('/api/v1/roles', $json['paths']);
        $this->assertArrayHasKey('/api/v1/users', $json['paths']);
        $this->assertArrayHasKey('/api/v1/dishes', $json['paths']);
        $this->assertArrayHasKey('/api/v1/dishes/{id}', $json['paths']);
        $this->assertArrayHasKey('/api/v1/dish-compositions', $json['paths']);
        $this->assertArrayHasKey('/api/v1/dish-compositions/{id}', $json['paths']);
        $this->assertArrayHasKey('/api/v1/menus', $json['paths']);
        $this->assertArrayHasKey('/api/v1/menu-dishes', $json['paths']);
        $this->assertArrayHasKey('/api/v1/menu-dishes/{id}', $json['paths']);
        $this->assertArrayHasKey('/api/v1/daily-patients', $json['paths']);
        $this->assertArrayHasKey('/api/v1/daily-patients/{service_date}', $json['paths']);
        $this->assertArrayHasKey('/api/v1/dashboard', $json['paths']);
        $this->assertArrayHasKey('/api/v1/stock-transactions', $json['paths']);
        $this->assertArrayHasKey('/api/v1/spk/basah/history', $json['paths']);

        $this->assertArrayHasKey('schemas', $json['components']);
        $this->assertArrayHasKey('TransactionTypeCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('ApprovalStatusCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('MealTimeCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('Dish', $json['components']['schemas']);
        $this->assertArrayHasKey('DishCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('DishComposition', $json['components']['schemas']);
        $this->assertArrayHasKey('DishCompositionCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('MenuSummary', $json['components']['schemas']);
        $this->assertArrayHasKey('MenuCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('MenuSlot', $json['components']['schemas']);
        $this->assertArrayHasKey('MenuDishCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('DailyPatientCollectionResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('DailyPatientMutationResponse', $json['components']['schemas']);
        $this->assertArrayHasKey('LookupCollectionMeta', $json['components']['schemas']);
        $this->assertArrayHasKey('properties', $json['components']['schemas']['LookupCollectionMeta']);
        $this->assertArrayHasKey('paginated', $json['components']['schemas']['LookupCollectionMeta']['properties']);
        $this->assertArrayHasKey('MenuCoreCollectionMeta', $json['components']['schemas']);
        $this->assertArrayHasKey('properties', $json['components']['schemas']['MenuCoreCollectionMeta']);
        $this->assertArrayHasKey('paginated', $json['components']['schemas']['MenuCoreCollectionMeta']['properties']);
    }

    private function assertSpecExamplesAreSanitized(string $body): void
    {
        $this->assertStringNotContainsString('password123', $body);
        $this->assertStringNotContainsString('newpassword123', $body);
        $this->assertStringNotContainsString('admin@example.com', $body);
        $this->assertStringNotContainsString('"username":"admin"', $body);
    }

    private function writeCache(string $json, int $mtime): void
    {
        $directory = dirname($this->cachePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->cachePath, $json, LOCK_EX);
        touch($this->cachePath, $mtime);
        clearstatcache(true, $this->cachePath);
        Services::reset(true);
    }

    private function rememberSourceFileMtime(string $file): void
    {
        if (! array_key_exists($file, $this->sourceFileMtimes)) {
            $this->sourceFileMtimes[$file] = (int) filemtime($file);
        }
    }
}
