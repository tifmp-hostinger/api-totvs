<?php

declare(strict_types=1);

namespace FMP\RMApi\Controllers;

use FMP\RMApi\Helpers\Json;
use FMP\RMApi\Helpers\Validation;
use FMP\RMApi\Services\AlunoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AlunoController
{
    public function __construct(private readonly AlunoService $alunoService)
    {
    }

    /** GET /alunos/{codcoligada}/{codpessoa} */
    public function buscar(Request $request, Response $response, array $args = []): Response
    {
        $aluno = $this->alunoService->buscar($args['codpessoa'], $args['codcoligada']);

        if ($aluno === null) {
            return Json::notFound('Não foi encontrado cadastro de aluno');
        }

        return Json::success('Cadastro de aluno encontrado.', $aluno);
    }

    /**
     * POST /alunos — cria/atualiza o aluno de uma pessoa.
     * Body: { CODPESSOA, CODCOLIGADA, CODTIPOCURSO, CODFILIAL, CPF?, RNM? }
     */
    public function salvar(Request $request, Response $response, array $args = []): Response
    {
        $data = (array) $request->getParsedBody();

        $chave = $this->alunoService->salvar(
            Validation::ensureHasValue($data, 'CODPESSOA'),
            Validation::ensureHasValue($data, 'CODCOLIGADA'),
            Validation::ensureHasValue($data, 'CODTIPOCURSO'),
            Validation::ensureHasValue($data, 'CODFILIAL'),
            (string) ($data['CPF'] ?? ''),
            (string) ($data['RNM'] ?? '')
        );

        return Json::success('Aluno gravado com sucesso.', ['CHAVE' => $chave], 201);
    }
}
