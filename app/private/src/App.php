<?php
declare(strict_types=1);

namespace AlgebraEngine;

use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Provider\CurlTransport;
use AlgebraEngine\Provider\HttpTransport;
use AlgebraEngine\Provider\OpenAiCompatibleProvider;
use AlgebraEngine\Provider\ProviderAdapter;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Report\TutorReport;
use AlgebraEngine\Storage\Database;
use AlgebraEngine\Storage\Store;
use AlgebraEngine\Support\Clock;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Support\SystemClock;
use AlgebraEngine\Tutor\AnswerCheck;
use AlgebraEngine\Tutor\MockTutor;
use AlgebraEngine\Tutor\PatternMatcher;
use AlgebraEngine\Tutor\ProblemFactory;
use AlgebraEngine\Tutor\ProblemVerifier;
use AlgebraEngine\Tutor\Recipes;
use AlgebraEngine\Tutor\TutorCoordinator;
use AlgebraEngine\Tutor\TutorEngine;
use AlgebraEngine\Tutor\TutorProjection;
use AlgebraEngine\Tutor\TutorPrompt;

/** Builds the object graph from private configuration. */
final class App
{
    public const ARCHITECTURE_FILE = 'Algebra_Constraint_Architecture_v1.3.md';

    public Store $store;
    public CurriculumRepository $curriculum;
    public ItemFactory $factory;
    public ProblemFactory $problems;
    public SchemaValidator $schemas;
    public ProviderAdapter $provider;
    public TutorPrompt $prompt;
    public TutorCoordinator $ai;
    public TutorEngine $engine;
    public TutorProjection $projection;
    public Clock $clock;
    public string $architecture;

    public function __construct(
        public Config $config,
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
        $this->problems = new ProblemFactory($this->factory);
        $this->schemas = new SchemaValidator($res . '/contracts');
        $this->architecture = (string) file_get_contents($res . '/architecture/' . self::ARCHITECTURE_FILE);
        $this->provider = $provider ?? ($config->isMock()
            ? new MockTutor($config->string('mock_fault_file'))
            : new OpenAiCompatibleProvider($config, $transport ?? new CurlTransport()));
        $this->prompt = new TutorPrompt($this->schemas);
        $patterns = new PatternMatcher($this->factory);
        $this->ai = new TutorCoordinator($this->provider, $this->prompt, $this->schemas, new ProblemVerifier($this->problems, $patterns), $this->architecture, $config->int('provider_result_max'));
        $this->projection = new TutorProjection($this->curriculum, $this->schemas);
        $this->engine = new TutorEngine(
            $config,
            $this->store,
            $this->curriculum,
            $this->problems,
            new Recipes($this->problems),
            new AnswerCheck($this->factory),
            $this->ai,
            $this->schemas,
            $this->projection,
            new TutorReport($this->curriculum, $this->schemas),
            $this->clock,
            $rng,
        );
    }
}
