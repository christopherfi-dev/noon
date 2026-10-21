<?php
declare(strict_types=1);

namespace AlgebraEngine;

use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Items\AcceptanceGate;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Items\SocraticPlanner;
use AlgebraEngine\Provider\CurlTransport;
use AlgebraEngine\Provider\HttpTransport;
use AlgebraEngine\Provider\MockProvider;
use AlgebraEngine\Provider\ModelCoordinator;
use AlgebraEngine\Provider\OpenAiCompatibleProvider;
use AlgebraEngine\Provider\PromptAssembler;
use AlgebraEngine\Provider\ProviderAdapter;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Report\ReportBuilder;
use AlgebraEngine\Storage\Database;
use AlgebraEngine\Storage\Store;
use AlgebraEngine\Support\Clock;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Support\SystemClock;
use AlgebraEngine\Workflow\Engine;
use AlgebraEngine\Workflow\PublicProjection;
use AlgebraEngine\Workflow\TransitionGuard;

/** Builds the object graph from private configuration. */
final class App
{
    public readonly Store $store;
    public readonly CurriculumRepository $curriculum;
    public readonly ItemFactory $factory;
    public readonly SchemaValidator $schemas;
    public readonly ProviderAdapter $provider;
    public readonly PromptAssembler $assembler;
    public readonly ModelCoordinator $model;
    public readonly Engine $engine;
    public readonly PublicProjection $projection;
    public readonly TransitionGuard $guard;
    public readonly Clock $clock;
    public readonly string $architecture;

    public function __construct(
        public readonly Config $config,
        ?Clock $clock = null,
        ?ProviderAdapter $provider = null,
        ?CurriculumRepository $curriculum = null,
        ?RandomSource $rng = null,
        ?HttpTransport $transport = null,
    ) {
        $res = $config->string('resources_dir');
        $this->clock = $clock ?? new SystemClock();
        $this->store = new Store(new Database($config->string('db_path'), dirname(__DIR__) . '/migrations'));
        $this->curriculum = $curriculum ?? CurriculumRepository::fromFile($res . '/curriculum/algebra-map.json');
        $this->factory = new ItemFactory();
        $this->schemas = new SchemaValidator($res . '/contracts');
        $this->architecture = (string) file_get_contents($res . '/architecture/Algebra_Constraint_Architecture_v1.2.md');
        $this->provider = $provider ?? ($config->isMock()
            ? new MockProvider($this->factory, $config->string('mock_fault_file'))
            : new OpenAiCompatibleProvider($config, $transport ?? new CurlTransport()));
        $this->guard = new TransitionGuard($res . '/contracts/workflow-policy.json');
        $this->assembler = new PromptAssembler($this->schemas);
        $this->model = new ModelCoordinator(
            $this->provider,
            $this->assembler,
            $this->schemas,
            new AcceptanceGate($this->factory, $this->schemas),
            $this->curriculum,
            $this->guard,
            $this->architecture,
            $config->int('provider_result_max'),
        );
        $this->projection = new PublicProjection($this->curriculum, $this->schemas);
        $this->engine = new Engine(
            $config,
            $this->store,
            $this->curriculum,
            $this->factory,
            new SocraticPlanner($this->factory),
            $this->model,
            $this->guard,
            new ReportBuilder($this->curriculum, $this->schemas),
            $this->schemas,
            $this->projection,
            $this->clock,
            $rng,
        );
    }
}
