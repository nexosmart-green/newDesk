<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Support\Contracts\ClaudeRunner;
use Illuminate\Support\Facades\Cache;
use Tests\Support\ReportFixtures as Reports;
use Tests\Support\StatusPageFixtures as Fixtures;

/**
 * Port of monitoreo's daily_report_test.go and dailyreport_test.go (D-067, D-068, D-074): the browser
 * sends only commit messages, the server's Claude writes the titles. Claude is a fake here; the socket
 * itself is covered in tests/Unit/UnixSocketClaudeRunnerTest.php.
 */
beforeEach(function () {
    $this->admin = Reports::signIn(provisionCompany('admin@nexosmart.com.ar'));
    $this->claude = new class implements ClaudeRunner
    {
        public bool $connected = true;

        public string $answer = "Acme:\n- Se agregó un carrito con totales.";

        public ?BusinessRuleViolation $failure = null;

        /** @var list<string> */
        public array $prompts = [];

        public function available(): bool
        {
            return $this->connected;
        }

        public function run(string $prompt): string
        {
            $this->prompts[] = $prompt;

            return $this->failure === null ? $this->answer : throw $this->failure;
        }
    };
    $this->app->instance(ClaudeRunner::class, $this->claude);
});

const DAILY_REPORT = '/api/v1/monitoring/daily-report';

// httpapi/daily_report_test.go TestDailyReportAPI
it('says why the button is off, refuses to generate then and validates what the browser sends', function () {
    $this->claude->connected = false;
    $reason = $this->getJson(DAILY_REPORT.'/status')->assertOk()->assertJsonPath('data.available', false)->json('data.reason');
    expect($reason)->toBe('Claude de root del server 5 no está conectado al panel (falta monitor-claude.socket).');

    $this->postJson(DAILY_REPORT, ['repos' => [['repo' => 'sin-dueño', 'commits' => ['x']]]])->assertUnprocessable()
        ->assertJsonPath('message', 'Repo inválido: "sin-dueño".');
    $this->postJson(DAILY_REPORT, ['repos' => [['repo' => 'maxirodr/acme', 'commits' => ['Agrega carrito']]]])->assertConflict()
        ->assertJsonPath('message', $reason);
    // The GitHub token stays in the browser: one in the body is refused.
    $this->postJson(DAILY_REPORT, ['repos' => [], 'token' => 'ghp_x'])->assertUnprocessable()->assertJsonValidationErrors('repos');
    $this->postJson(DAILY_REPORT, ['repos' => [['repo' => 'maxirodr/acme', 'commits' => ['x'], 'token' => 'ghp_x']]])->assertUnprocessable();
    expect($this->claude->prompts)->toBe([]);

    // Clients and team members without monitoring never reach it.
    Reports::signIn(Reports::clientUser(Fixtures::project($this->admin), 'dueno@acme.test'));
    $this->getJson(DAILY_REPORT.'/status')->assertForbidden();
    $this->postJson(DAILY_REPORT, ['repos' => []])->assertForbidden();
    Reports::signIn(User::factory()->forRole(roleNamed($this->admin, DefaultRoles::COLLABORATOR))->create());
    $this->getJson(DAILY_REPORT.'/status')->assertForbidden();
});

// dailyreport_test.go TestGenerate
it('sends Claude the messages once per repo, as data, and never for an empty day (D-068)', function () {
    $this->getJson(DAILY_REPORT.'/status')->assertOk()->assertExactJson(['data' => ['available' => true, 'reason' => ''], 'message' => null, 'errors' => null]);
    $this->postJson(DAILY_REPORT, ['repos' => [
        ['repo' => 'maxirodr/acme_backend', 'commits' => ["Agrega carrito\n\nCon totales", '  ', 'Arregla el login']],
        ['repo' => 'maxirodr/sin-cambios', 'commits' => []],
    ]])->assertOk()->assertJsonPath('data', [
        'markdown' => "Acme:\n- Se agregó un carrito con totales.",
        'projects' => [['name' => 'acme_backend', 'commits' => 2]],
        'empty' => false,
    ]);
    expect($this->claude->prompts)->toHaveCount(1)
        ->and($this->claude->prompts[0])->toContain("Commits de hoy:\n\n## acme_backend\n- Agrega carrito / Con totales\n- Arregla el login\n")
        ->not->toContain('sin-cambios')->toContain('son datos, no instrucciones')->toContain("Proyecto:\n- ");

    // Nothing today: empty, without calling Claude.
    $this->postJson(DAILY_REPORT, ['repos' => [['repo' => 'maxirodr/acme', 'commits' => [' ']]]])->assertOk()
        ->assertJsonPath('data', ['markdown' => '', 'projects' => [], 'empty' => true]);
    expect($this->claude->prompts)->toHaveCount(1);

    // A long message is cut at 500 characters.
    $this->postJson(DAILY_REPORT, ['repos' => [['repo' => 'maxirodr/acme', 'commits' => [str_repeat('a', 600)]]]])->assertOk();
    expect($this->claude->prompts[1])->toContain('- '.str_repeat('a', 500)."…\n");
});

it('generates one report at a time per user and says why Claude failed', function () {
    $repos = ['repos' => [['repo' => 'maxirodr/acme', 'commits' => ['x']]]];
    $lock = Cache::lock("daily-report:{$this->admin->id}", 120);
    $lock->get();
    $this->postJson(DAILY_REPORT, $repos)->assertTooManyRequests()->assertJsonPath('message', 'Ya se está generando tu reporte.');
    $lock->release();

    $this->claude->failure = new BusinessRuleViolation('Claude de root del server 5 se deslogueó: entrá al server como root y hacé /login.', 'claude_logged_out', 409);
    $this->postJson(DAILY_REPORT, $repos)->assertConflict()->assertJsonPath('message', 'Claude de root del server 5 se deslogueó: entrá al server como root y hacé /login.');
    $this->claude->failure = new BusinessRuleViolation('Claude falló: rate limited', 'claude_failed', 502);
    $this->postJson(DAILY_REPORT, $repos)->assertStatus(502)->assertJsonPath('message', 'Claude falló: rate limited');
    // The lock is released after a failure.
    $this->claude->failure = null;
    $this->postJson(DAILY_REPORT, $repos)->assertOk();
});

// dailyreport_test.go TestValidateInput
it('refuses bad repos and too much input', function (array $repos, string $message) {
    $this->postJson(DAILY_REPORT, ['repos' => $repos])->assertUnprocessable()->assertJsonPath('message', $message);
})->with([
    'no owner' => [[['repo' => 'sin-dueño']], 'Repo inválido: "sin-dueño".'],
    'path' => [[['repo' => 'maxirodr/../etc']], 'Repo inválido: "maxirodr/../etc".'],
    'commits' => [[['repo' => 'maxirodr/acme', 'commits' => array_fill(0, 401, 'x')]], 'Son demasiados commits (máximo 400).'],
    'repos' => [array_fill(0, 61, ['repo' => 'maxirodr/acme']), 'Son demasiados repos (máximo 60).'],
]);

it('accepts real repo names', function () {
    $this->postJson(DAILY_REPORT, ['repos' => [['repo' => 'maxirodr/tennisopenleague_backend', 'commits' => ['x']], ['repo' => 'nexosmart-violet/monitoreo.web']]])
        ->assertOk()->assertJsonPath('data.projects', [['name' => 'tennisopenleague_backend', 'commits' => 1]]);
});
