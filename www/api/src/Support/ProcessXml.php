<?php

declare(strict_types=1);

namespace FMP\RMApi\Support;

/**
 * Templates dos XMLs de processos do RM (wsProcess / ExecuteWithXMLParams).
 *
 * Os XMLs foram herdados do legado (capturas de execuções reais do RM) e
 * mantidos estruturalmente idênticos para preservar compatibilidade de
 * desserialização (DataContract). Apenas os valores de sessão foram
 * neutralizados/parametrizados: ExecutionId, ScheduleDateTime, HostName,
 * Ip, NetworkUser e competência.
 *
 * Observação herdada do legado: alguns valores permanecem fixos de propósito
 * (ex.: $CODCOLIGADA=1 e $CODTIPOCURSO=2 no contexto dos processos,
 * CodStatus=23 = pré-matrícula, CodTipoMat=7).
 */
class ProcessXml
{
    /**
     * Remove a indentação comum do heredoc e qualquer espaço em branco
     * antes da declaração <?xml ...?> — o desserializador .NET do RM exige
     * que a declaração seja o primeiro caractere do documento.
     */
    private static function dedent(string $xml): string
    {
        $lines = explode("\n", $xml);

        $indent = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $len = strlen($line) - strlen(ltrim($line, ' '));
            $indent = $indent === null ? $len : min($indent, $len);
        }

        if ($indent > 0) {
            foreach ($lines as $i => $line) {
                $lines[$i] = substr($line, $indent) ?: '';
            }
        }

        return trim(implode("\n", $lines));
    }

    private static function guid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Processo "Matricular aluno" (EduMatriculaProcData):
     * matrícula no período letivo + geração do contrato.
     */
    public static function matriculaPeriodoLetivo(
        string|int $codColigada,
        string|int $codFilial,
        string|int $idHabilitacaoFilial,
        string|int $idPerlet,
        string $ra,
        string $codTurma,
        string $codPlanoPagamento,
        string $now
    ): string {
        $executionId = self::guid();
        $scheduleDateTime = date('Y-m-d\TH:i:s.0000000P');
        $competencia = date('m/Y');

        $xml = <<<XML
                <?xml version="1.0" encoding="utf-16"?>
                        <EduMatriculaParamsProc z:Id="i1" xmlns="http://www.totvs.com.br/RM/" xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:z="http://schemas.microsoft.com/2003/10/Serialization/">
                        <ActionModule xmlns="http://www.totvs.com/">S</ActionModule>
                        <ActionName xmlns="http://www.totvs.com/">EduMatriculaProcAction</ActionName>
                        <CanParallelize xmlns="http://www.totvs.com/">true</CanParallelize>
                        <CanSendMail xmlns="http://www.totvs.com/">false</CanSendMail>
                        <CanWaitSchedule xmlns="http://www.totvs.com/">false</CanWaitSchedule>
                        <CodUsuario xmlns="http://www.totvs.com/">integra.eduvem</CodUsuario>
                        <ConnectionId i:nil="true" xmlns="http://www.totvs.com/" />
                        <ConnectionString i:nil="true" xmlns="http://www.totvs.com/" />
                        <Context z:Id="i2" xmlns="http://www.totvs.com/" xmlns:a="http://www.totvs.com.br/RM/">
                            <a:_params xmlns:b="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$EXERCICIOFISCAL</b:Key>
                                <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODLOCPRT</b:Key>
                                <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODTIPOCURSO</b:Key>
                                <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">2</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$EDUTIPOUSR</b:Key>
                                <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUNIDADEBIB</b:Key>
                                <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODCOLIGADA</b:Key>
                                <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$RHTIPOUSR</b:Key>
                                <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODIGOEXTERNO</b:Key>
                                <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODSISTEMA</b:Key>
                                <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">S</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUSUARIOSERVICO</b:Key>
                                <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema" />
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUSUARIO</b:Key>
                                <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">integra.eduvem</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$IDPRJ</b:Key>
                                <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CHAPAFUNCIONARIO</b:Key>
                                <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            <b:KeyValueOfanyTypeanyType>
                                <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODFILIAL</b:Key>
                                <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">{$codFilial}</b:Value>
                            </b:KeyValueOfanyTypeanyType>
                            </a:_params>
                            <a:Environment>DotNet</a:Environment>
                        </Context>
                        <CustomData i:nil="true" xmlns="http://www.totvs.com/" />
                        <DisableIsolateProcess xmlns="http://www.totvs.com/">false</DisableIsolateProcess>
                        <DriverType i:nil="true" xmlns="http://www.totvs.com/" />
                        <ExecutionId xmlns="http://www.totvs.com/">{$executionId}</ExecutionId>
                        <FailureMessage xmlns="http://www.totvs.com/">Falha na execução do processo</FailureMessage>
                        <FriendlyLogs i:nil="true" xmlns="http://www.totvs.com/" />
                        <HideProgressDialog xmlns="http://www.totvs.com/">false</HideProgressDialog>
                        <HostName xmlns="http://www.totvs.com/">integra-rm-api</HostName>
                        <Initialized xmlns="http://www.totvs.com/">true</Initialized>
                        <Ip xmlns="http://www.totvs.com/">127.0.0.1</Ip>
                        <IsolateProcess xmlns="http://www.totvs.com/">false</IsolateProcess>
                        <JobID xmlns="http://www.totvs.com/">
                            <Children />
                            <ExecID>1</ExecID>
                            <ID>900472</ID>
                            <IsPriorityJob>false</IsPriorityJob>
                        </JobID>
                        <JobServerHostName xmlns="http://www.totvs.com/">114384-core-instance-N-RM-P-CAQB4Z-1-731591WIN-Z2</JobServerHostName>
                        <MasterActionName xmlns="http://www.totvs.com/">EduHabilitacaoAlunoAction</MasterActionName>
                        <MaximumQuantityOfPrimaryKeysPerProcess xmlns="http://www.totvs.com/">1000</MaximumQuantityOfPrimaryKeysPerProcess>
                        <MinimumQuantityOfPrimaryKeysPerProcess xmlns="http://www.totvs.com/">1</MinimumQuantityOfPrimaryKeysPerProcess>
                        <NetworkUser xmlns="http://www.totvs.com/">integra.eduvem</NetworkUser>
                        <NotifyEmail xmlns="http://www.totvs.com/">false</NotifyEmail>
                        <NotifyEmailList i:nil="true" xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <NotifyFluig xmlns="http://www.totvs.com/">false</NotifyFluig>
                        <OnlineMode xmlns="http://www.totvs.com/">false</OnlineMode>
                        <PrimaryKeyList xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                            <a:ArrayOfanyType>
                            <a:anyType i:type="b:short" xmlns:b="http://www.w3.org/2001/XMLSchema">{$codColigada}</a:anyType>
                            <a:anyType i:type="b:int" xmlns:b="http://www.w3.org/2001/XMLSchema">{$idHabilitacaoFilial}</a:anyType>
                            <a:anyType i:type="b:string" xmlns:b="http://www.w3.org/2001/XMLSchema">{$ra}</a:anyType>
                            </a:ArrayOfanyType>
                        </PrimaryKeyList>
                        <PrimaryKeyNames xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                            <a:string>CODCOLIGADA</a:string>
                            <a:string>IDHABILITACAOFILIAL</a:string>
                            <a:string>RA</a:string>
                        </PrimaryKeyNames>
                        <PrimaryKeyTableName xmlns="http://www.totvs.com/">SHabilitacaoAluno</PrimaryKeyTableName>
                        <ProcessName xmlns="http://www.totvs.com/">Matricular aluno</ProcessName>
                        <QuantityOfSplits xmlns="http://www.totvs.com/">1</QuantityOfSplits>
                        <SaveLogInDatabase xmlns="http://www.totvs.com/">true</SaveLogInDatabase>
                        <SaveParamsExecution xmlns="http://www.totvs.com/">false</SaveParamsExecution>
                        <ScheduleDateTime xmlns="http://www.totvs.com/">{$scheduleDateTime}</ScheduleDateTime>
                        <Scheduler xmlns="http://www.totvs.com/">JobMonitor</Scheduler>
                        <SendMail xmlns="http://www.totvs.com/">false</SendMail>
                        <ServerName xmlns="http://www.totvs.com/">EduMatriculaProcData</ServerName>
                        <ServiceInterface i:type="b:RuntimeType" z:FactoryType="c:UnitySerializationHolder" xmlns="http://www.totvs.com/" xmlns:a="http://schemas.datacontract.org/2004/07/System" xmlns:b="-mscorlib, Version=4.0.0.0, Culture=neutral, PublicKeyToken=b77a5c561934e089-System-System.RuntimeType" xmlns:c="-mscorlib, Version=4.0.0.0, Culture=neutral, PublicKeyToken=b77a5c561934e089-System-System.UnitySerializationHolder">
                            <Data i:type="d:string" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">RM.Edu.Interfaces.IEduMatriculaProc</Data>
                            <UnityType i:type="d:int" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">4</UnityType>
                            <AssemblyName i:type="d:string" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">RM.Edu.Interfaces.Intf, Version=12.1.2502.152, Culture=neutral, PublicKeyToken=null</AssemblyName>
                        </ServiceInterface>
                        <ShouldParallelize xmlns="http://www.totvs.com/">false</ShouldParallelize>
                        <ShowReExecuteButton xmlns="http://www.totvs.com/">true</ShowReExecuteButton>
                        <StatusMessage i:nil="true" xmlns="http://www.totvs.com/" />
                        <SuccessMessage xmlns="http://www.totvs.com/">Processo executado com sucesso</SuccessMessage>
                        <SyncExecution xmlns="http://www.totvs.com/">false</SyncExecution>
                        <UseJobMonitor xmlns="http://www.totvs.com/">true</UseJobMonitor>
                        <UserName xmlns="http://www.totvs.com/">integra.eduvem</UserName>
                        <WaitSchedule xmlns="http://www.totvs.com/">false</WaitSchedule>
                        <CadastrarDisciplinas>true</CadastrarDisciplinas>
                        <MatricPLParams z:Id="i3">
                            <AlteraMatrizContratoOriginal>false</AlteraMatrizContratoOriginal>
                            <ApagarNumeroDiario>false</ApagarNumeroDiario>
                            <ArquivoRelatorioContrato i:nil="true" />
                            <CR i:nil="true" />
                            <CadastrarContrato>true</CadastrarContrato>
                            <CancelarLancamentos>true</CancelarLancamentos>
                            <CarteiraEmitida>false</CarteiraEmitida>
                            <ChaveRMRegistroMasterTAE i:nil="true" />
                            <ClientIP i:nil="true" />
                            <CobrarDocsTipoIngressoRematriculaEB>true</CobrarDocsTipoIngressoRematriculaEB>
                            <CodColigada>{$codColigada}</CodColigada>
                            <CodContrato i:nil="true" />
                            <CodFilial>{$codFilial}</CodFilial>
                            <CodFormula i:nil="true" />
                            <CodInstDestino i:nil="true" />
                            <CodMotivo i:nil="true" />
                            <CodMotivoTransferencia i:nil="true" />
                            <CodPlanoPgto>{$codPlanoPagamento}</CodPlanoPgto>
                            <CodStatus>23</CodStatus>
                            <CodStatusNovo i:nil="true" />
                            <CodStatusPendenteDisc i:nil="true" />
                            <CodStatusPendentePL i:nil="true" />
                            <CodStatusRes i:nil="true" />
                            <CodTipoCurso>2</CodTipoCurso>
                            <CodTipoMat>7</CodTipoMat>
                            <CodTurma>{$codTurma}</CodTurma>
                            <CodTurmaAnterior i:nil="true" />
                            <CodUsuario>integra.eduvem</CodUsuario>
                            <ColigadaRelatBoleto i:nil="true" />
                            <ColigadaRelatContrato i:nil="true" />
                            <ContratosCanceladosContabilizados xmlns:a="http://www.totvs.com/" />
                            <ContratosTemp xmlns:a="http://www.totvs.com/" />
                            <CopiarDescontoPorAntecipacao>false</CopiarDescontoPorAntecipacao>
                            <CopiarRespFinanceiroContrato>false</CopiarRespFinanceiroContrato>
                            <CopiarVencimentos>false</CopiarVencimentos>
                            <CotaFinal i:nil="true" />
                            <CotaInicial i:nil="true" />
                            <DadosRelatorioManifesto i:nil="true" />
                            <DataCancelamentoContrato i:nil="true" />
                            <DataCancelamentoParcelas i:nil="true" />
                            <DataFinalParc i:nil="true" />
                            <DataIngresso i:nil="true" />
                            <DataInicialParc i:nil="true" />
                            <DataMatricula>{$now}</DataMatricula>
                            <DataMatriculaAnterior i:nil="true" />
                            <DataMatriculaEncerra i:nil="true" />
                            <DataMatriculaEncerraAnterior i:nil="true" />
                            <DataMatriculaEncerraNova i:nil="true" />
                            <DataMatriculaNova i:nil="true" />
                            <DiaFixo>Nao</DiaFixo>
                            <DiaVencimento i:nil="true" />
                            <DiasVencimentoPrimeiraParcela>0</DiasVencimentoPrimeiraParcela>
                            <Disciplinas />
                            <DtCompetenciaFinal>  /</DtCompetenciaFinal>
                            <DtCompetenciaFinalMov>{$competencia}</DtCompetenciaFinalMov>
                            <DtCompetenciaInicial>  /</DtCompetenciaInicial>
                            <DtCompetenciaInicialMov>{$competencia}</DtCompetenciaInicialMov>
                            <DtMatriculaPag i:nil="true" />
                            <DtResultado i:nil="true" />
                            <DtSolicitacaoAlteracao i:nil="true" />
                            <EmTransacao>false</EmTransacao>
                            <GeraManifesto>false</GeraManifesto>
                            <GerarContratoAssinado>false</GerarContratoAssinado>
                            <GerarLancamento>Nao</GerarLancamento>
                            <GerarLog>true</GerarLog>
                            <GerouContratoComPlano>true</GerouContratoComPlano>
                            <IDPS>0</IDPS>
                            <IdHabilitacaoFilial>{$idHabilitacaoFilial}</IdHabilitacaoFilial>
                            <IdHabilitacaoFilialOrigem i:nil="true" />
                            <IdPerLet>{$idPerlet}</IdPerLet>
                            <IdRelatBoleto i:nil="true" />
                            <IdRelatContrato i:nil="true" />
                            <Identificador />
                            <IsDesenturmacao>false</IsDesenturmacao>
                            <IsEnturmacao>false</IsEnturmacao>
                            <IsRematricula>false</IsRematricula>
                            <ListaItinerarioFormativo i:nil="true" />
                            <LogContrato z:Id="i4">
                            <ExceptionCount>0</ExceptionCount>
                            <ExceptionList xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                            <FooterMessageLog />
                            <HeaderMessageLog />
                            <Id>3388</Id>
                            <InformationCount>0</InformationCount>
                            <InformationList xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                            <Name>eduProcessLog3388</Name>
                            <SuccessLogCount>0</SuccessLogCount>
                            <WarningCount>0</WarningCount>
                            <WarningList xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                            </LogContrato>
                            <LogExcecoes z:Id="i5">
                            <Excecoes>
                                <EduExcecaoMatricula z:Id="i6">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>PLEncerrado</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i7">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ForaPeriodoMatricula</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i8">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>AlunoInadimplente</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i9">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>OcorrenciaAluno</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i10">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>TurmaCheia</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i11">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>FaltaDocObrigatorio</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i12">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>EmprestimoAtrasoBib</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i13">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>DebitoBiblioteca</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i14">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>StatusBloqAltSitMat</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i15">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ForaPeriodoTurma</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i16">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>StatusBloqMatricPL</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i17">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>StatusBloqTranc</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i18">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ForaPeriodoTranc</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i19">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>TrancPrimeiroPeriodo</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i20">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MaxPeriodosTranc</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i21">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>NumPeriodosTranc</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i22">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>AlterouNumero</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i23">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>StatusBloqDisc</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i24">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>DiscAtraso</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i25">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ChoqueHorarios</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i26">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>PreRequisito</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i27">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>CoRequisito</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i28">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>DisciplinaCursada</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i29">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>DisciplinaEmCurso</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i30">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MinCreditos</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i31">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MinDisciplinas</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i32">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MinCargaHoraria</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i33">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>AprovEstudos</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i34">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>LimiteMatricula</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i35">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>PercLimiteMat</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i36">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MinimoCreditosPL</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i37">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MaximoCreditosPL</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i38">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ParamCurso</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i39">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>JaMatriculadoPL</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i40">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ProcEmail</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i41">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ExcluiMatriculaComMovimento</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i42">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>PreRequisitoFormula</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i43">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>CoRequisitoFormula</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i44">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ParamMatricula</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i45">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>StatusBloqAltSitMatDisc</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i46">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MatriculaEmTurmaDiscGerencial</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i47">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>AlteraDataMatricula</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i48">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>DesenturmacaoTurmaMista</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i49">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>StatusBloqAltSitMatDiscPortal</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i50">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MatrizCurricularInativa</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i51">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>TurmaCheiaCorrequisito</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i52">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>SincUsuarioIntegracaoPergamum</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i53">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ErroConsultaPendenciaBiblioteca</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i54">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>PermiteMatOutraFilial</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i55">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>PermiteMatOutroNivelEnsino</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i56">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MatriculaComMovimentacao</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i57">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>IntegralizacaoNaoAtendidaMatriculaDisciplina</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i58">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>MinCreditosPeriodoNaoAtendidoIntegralizacaoAtendida</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i59">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>IntegralizacaoNaoAtendidaConclusaoCursoMudancaStatus</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i60">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>UltrapassouMaximoMatriculasItinerarioFormativo</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i61">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>RequisitosItinerarioNaoAtendido</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i62">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ForaPeriodoMatriculaNoItinerarioFormativo</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i63">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>PlanoPagtoDoContratoNaoDisponivelParaMtzDestinoNaTransfInterna</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i64">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>NaoPossuiMinimoMatriculasItinerarioFormativo</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i65">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>FaltaFiadorAprovado</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i66">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>TurmaNaoParametrizadaNaDispItinerarioParamPorCurso</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i67">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>RequisitoDeQtdDiscOuCHItinerarioNaoAtendido</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i68">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>RequisitoDeDisciplinasObrigatoriasNaoAtendido</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i69">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>RequisitoObrigatoriedadeMatriculasItinerarioFormativoNaoAtendido</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i70">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>CancelamentoOnlineMatriculaDisciplinaInconsistente</TipoExcecao>
                                </EduExcecaoMatricula>
                                <EduExcecaoMatricula z:Id="i71">
                                <GerouExcecao>false</GerouExcecao>
                                <IgnoraExcecao>false</IgnoraExcecao>
                                <Mensagem />
                                <TipoExcecao>ErroGenerico</TipoExcecao>
                                </EduExcecaoMatricula>
                            </Excecoes>
                            <NumExcecoes>0</NumExcecoes>
                            <NumExcecoesPermis>0</NumExcecoesPermis>
                            <Texto />
                            </LogExcecoes>
                            <MatriculaNovoPortal>false</MatriculaNovoPortal>
                            <MatriculaPortalItinerarioMenuExclusivo>false</MatriculaPortalItinerarioMenuExclusivo>
                            <MatriculaWeb>false</MatriculaWeb>
                            <MudancaStatus>false</MudancaStatus>
                            <MudancaTurma>false</MudancaTurma>
                            <NaoUsaFlexibilizacaoAlteraDataVencimento>false</NaoUsaFlexibilizacaoAlteraDataVencimento>
                            <NaoUsaFlexibilizacaoRemoverParcelasVencidas>false</NaoUsaFlexibilizacaoRemoverParcelasVencidas>
                            <NomeAluno i:nil="true" />
                            <NumCarteira />
                            <NumeroInscricao>0</NumeroInscricao>
                            <OrigemParcela i:nil="true" />
                            <ParametrosDiversos>
                            <AproveitarContratoNaMudancaTurma>false</AproveitarContratoNaMudancaTurma>
                            <BloquearAltStatusInadimplentes i:nil="true" />
                            <CarregarLstDocumentosCadaAluno>true</CarregarLstDocumentosCadaAluno>
                            <CarregarLstHabilitacaoesCadaAluno>true</CarregarLstHabilitacaoesCadaAluno>
                            <CarregarLstLancamentosCadaAluno>false</CarregarLstLancamentosCadaAluno>
                            <CarregarLstMatricIsoladaCadaAluno>true</CarregarLstMatricIsoladaCadaAluno>
                            <CarregarLstMtzAplicadaCursoHabAluno>true</CarregarLstMtzAplicadaCursoHabAluno>
                            <CarregarLstOcorrenciasCadaAluno>true</CarregarLstOcorrenciasCadaAluno>
                            <DataMatriculaUtilizadaDiscDestino>DisciplinaOrigem</DataMatriculaUtilizadaDiscDestino>
                            <IdPerLetCorrente i:nil="true" />
                            <IgnorarConflitoHorario i:nil="true" />
                            <IgnorarTurmaCheia i:nil="true" />
                            <IsEnturmacao>false</IsEnturmacao>
                            <MatriculaWeb>false</MatriculaWeb>
                            <MudancaStatus>false</MudancaStatus>
                            <NumeroMaximoPeriodosTrancados i:nil="true" />
                            <VerificarInadimplenciaBib>false</VerificarInadimplenciaBib>
                            <VerificarInadimplenciaFin>false</VerificarInadimplenciaFin>
                            </ParametrosDiversos>
                            <ParcelaFinal i:nil="true" />
                            <ParcelaInicial i:nil="true" />
                            <PendenteDocumento>Nao</PendenteDocumento>
                            <PendenteInadimplencia>Nao</PendenteInadimplencia>
                            <PendenteInadimplenciaBib>Nao</PendenteInadimplenciaBib>
                            <PendenteOcorrenciaBloqMatricula>Nao</PendenteOcorrenciaBloqMatricula>
                            <Periodo>1</Periodo>
                            <PermiteTransfInternaAlunoInadimplente>false</PermiteTransfInternaAlunoInadimplente>
                            <PodeRodarNumeracaoAutomatica>true</PodeRodarNumeracaoAutomatica>
                            <PossuiPendencia>false</PossuiPendencia>
                            <RA>{$ra}</RA>
                            <RematriculaEBasicoAjusteContratoHabFilial>false</RematriculaEBasicoAjusteContratoHabFilial>
                            <ResponsaveisFinanceirosContrato xmlns:a="http://www.totvs.com/" />
                            <ServicosRenovacaoRecorrencia />
                            <TextoContrato i:nil="true" />
                            <TipoOperacao>Inclusao</TipoOperacao>
                            <TipoSelecaoParcela>IdParcela</TipoSelecaoParcela>
                            <TokenAssinaturaTAE i:nil="true" />
                            <TransferenciaInterna>false</TransferenciaInterna>
                            <TurnosDiferentes>false</TurnosDiferentes>
                            <UsarPlanoPgtoParametrizacaoCurso>false</UsarPlanoPgtoParametrizacaoCurso>
                            <ValidarInadimplenciaBiblioteca>true</ValidarInadimplenciaBiblioteca>
                            <ViaCarteira />
                            <grupoRelat i:nil="true" />
                        </MatricPLParams>
                        <MatricularDisc>Nao</MatricularDisc>
                        </EduMatriculaParamsProc>

XML;

        return self::dedent($xml);
    }

    /**
     * Processo "Matricular aluno nas disciplinas" (EduMatriculaProcData):
     * enturmação do aluno em uma turma/disciplina.
     *
     * @param array $groupToInclude linha da consulta INT.EDUVEM.00019
     *        (CODCOLIGADA, CODTIPOCURSO, CODFILIAL, CODTURMA, CODDISC,
     *         IDTURMADISC, IDHABILITACAOFILIAL)
     */
    public static function matriculaDisciplina(
        array $groupToInclude,
        string|int $idPerlet,
        string|int $idHabilitacaoFilial,
        string $ra,
        string|int $codFilial,
        string $codTurma,
        string $now
    ): string {
        $executionId = self::guid();
        $scheduleDateTime = date('Y-m-d\TH:i:s.0000000P');

        $xml = <<<XML
                <?xml version="1.0" encoding="utf-16"?>
                    <EduMatriculaParamsProc z:Id="i1" xmlns="http://www.totvs.com.br/RM/" xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:z="http://schemas.microsoft.com/2003/10/Serialization/">
                    <ActionModule xmlns="http://www.totvs.com/">S</ActionModule>
                    <ActionName xmlns="http://www.totvs.com/">EduMatriculaDiscAction</ActionName>
                    <CanParallelize xmlns="http://www.totvs.com/">true</CanParallelize>
                    <CanSendMail xmlns="http://www.totvs.com/">false</CanSendMail>
                    <CanWaitSchedule xmlns="http://www.totvs.com/">false</CanWaitSchedule>
                    <CodUsuario xmlns="http://www.totvs.com/">integra.eduvem</CodUsuario>
                    <ConnectionId i:nil="true" xmlns="http://www.totvs.com/" />
                    <ConnectionString i:nil="true" xmlns="http://www.totvs.com/" />
                    <Context z:Id="i2" xmlns="http://www.totvs.com/" xmlns:a="http://www.totvs.com.br/RM/">
                        <a:_params xmlns:b="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$EXERCICIOFISCAL</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODLOCPRT</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODTIPOCURSO</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">{$groupToInclude['CODTIPOCURSO']}</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$EDUTIPOUSR</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUNIDADEBIB</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODCOLIGADA</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">{$groupToInclude['CODCOLIGADA']}</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$RHTIPOUSR</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODIGOEXTERNO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODSISTEMA</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">S</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUSUARIOSERVICO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema" />
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUSUARIO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">integra.eduvem</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$IDPRJ</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CHAPAFUNCIONARIO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODFILIAL</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">{$groupToInclude['CODFILIAL']}</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        </a:_params>
                        <a:Environment>DotNet</a:Environment>
                    </Context>
                    <CustomData i:nil="true" xmlns="http://www.totvs.com/" />
                    <DisableIsolateProcess xmlns="http://www.totvs.com/">false</DisableIsolateProcess>
                    <DriverType i:nil="true" xmlns="http://www.totvs.com/" />
                    <ExecutionId xmlns="http://www.totvs.com/">{$executionId}</ExecutionId>
                    <FailureMessage xmlns="http://www.totvs.com/">Falha na execu  o do processo</FailureMessage>
                    <FriendlyLogs i:nil="true" xmlns="http://www.totvs.com/" />
                    <HideProgressDialog xmlns="http://www.totvs.com/">false</HideProgressDialog>
                    <HostName xmlns="http://www.totvs.com/">integra-rm-api</HostName>
                    <Initialized xmlns="http://www.totvs.com/">true</Initialized>
                    <Ip xmlns="http://www.totvs.com/">127.0.0.1</Ip>
                    <IsolateProcess xmlns="http://www.totvs.com/">false</IsolateProcess>
                    <JobID xmlns="http://www.totvs.com/">
                        <Children />
                        <ExecID>1</ExecID>
                        <ID>905486</ID>
                        <IsPriorityJob>false</IsPriorityJob>
                    </JobID>
                    <JobServerHostName xmlns="http://www.totvs.com/">114384-elastic-service-N-RM-P-CAQB4Z-2-c7f4WIN-Z2</JobServerHostName>
                    <MasterActionName xmlns="http://www.totvs.com/">EduMatricPLAction</MasterActionName>
                    <MaximumQuantityOfPrimaryKeysPerProcess xmlns="http://www.totvs.com/">1000</MaximumQuantityOfPrimaryKeysPerProcess>
                    <MinimumQuantityOfPrimaryKeysPerProcess xmlns="http://www.totvs.com/">1</MinimumQuantityOfPrimaryKeysPerProcess>
                    <NetworkUser xmlns="http://www.totvs.com/">integra.eduvem</NetworkUser>
                    <NotifyEmail xmlns="http://www.totvs.com/">false</NotifyEmail>
                    <NotifyEmailList i:nil="true" xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                    <NotifyFluig xmlns="http://www.totvs.com/">false</NotifyFluig>
                    <OnlineMode xmlns="http://www.totvs.com/">false</OnlineMode>
                    <PrimaryKeyList xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                        <a:ArrayOfanyType>
                        <a:anyType i:type="b:short" xmlns:b="http://www.w3.org/2001/XMLSchema">{$groupToInclude['CODCOLIGADA']}</a:anyType>
                        <a:anyType i:type="b:int" xmlns:b="http://www.w3.org/2001/XMLSchema">{$idPerlet}</a:anyType>
                        <a:anyType i:type="b:int" xmlns:b="http://www.w3.org/2001/XMLSchema">{$groupToInclude['IDHABILITACAOFILIAL']}</a:anyType>
                        <a:anyType i:type="b:string" xmlns:b="http://www.w3.org/2001/XMLSchema">{$ra}</a:anyType>
                        </a:ArrayOfanyType>
                    </PrimaryKeyList>
                    <PrimaryKeyNames xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                        <a:string>CODCOLIGADA</a:string>
                        <a:string>IDPERLET</a:string>
                        <a:string>IDHABILITACAOFILIAL</a:string>
                        <a:string>RA</a:string>
                    </PrimaryKeyNames>
                    <PrimaryKeyTableName xmlns="http://www.totvs.com/">SMatricPL</PrimaryKeyTableName>
                    <ProcessName xmlns="http://www.totvs.com/">Matricular aluno nas disciplinas</ProcessName>
                    <QuantityOfSplits xmlns="http://www.totvs.com/">1</QuantityOfSplits>
                    <SaveLogInDatabase xmlns="http://www.totvs.com/">true</SaveLogInDatabase>
                    <SaveParamsExecution xmlns="http://www.totvs.com/">false</SaveParamsExecution>
                    <ScheduleDateTime xmlns="http://www.totvs.com/">{$scheduleDateTime}</ScheduleDateTime>
                    <Scheduler xmlns="http://www.totvs.com/">JobMonitor</Scheduler>
                    <SendMail xmlns="http://www.totvs.com/">false</SendMail>
                    <ServerName xmlns="http://www.totvs.com/">EduMatriculaProcData</ServerName>
                    <ServiceInterface i:type="b:RuntimeType" z:FactoryType="c:UnitySerializationHolder" xmlns="http://www.totvs.com/" xmlns:a="http://schemas.datacontract.org/2004/07/System" xmlns:b="-mscorlib, Version=4.0.0.0, Culture=neutral, PublicKeyToken=b77a5c561934e089-System-System.RuntimeType" xmlns:c="-mscorlib, Version=4.0.0.0, Culture=neutral, PublicKeyToken=b77a5c561934e089-System-System.UnitySerializationHolder">
                        <Data i:type="d:string" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">RM.Edu.Interfaces.IEduMatriculaProc</Data>
                        <UnityType i:type="d:int" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">4</UnityType>
                        <AssemblyName i:type="d:string" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">RM.Edu.Interfaces.Intf, Version=12.1.2502.176, Culture=neutral, PublicKeyToken=null</AssemblyName>
                    </ServiceInterface>
                    <ShouldParallelize xmlns="http://www.totvs.com/">false</ShouldParallelize>
                    <ShowReExecuteButton xmlns="http://www.totvs.com/">true</ShowReExecuteButton>
                    <StatusMessage i:nil="true" xmlns="http://www.totvs.com/" />
                    <SuccessMessage xmlns="http://www.totvs.com/">Processo executado com sucesso</SuccessMessage>
                    <SyncExecution xmlns="http://www.totvs.com/">false</SyncExecution>
                    <UseJobMonitor xmlns="http://www.totvs.com/">true</UseJobMonitor>
                    <UserName xmlns="http://www.totvs.com/">integra.eduvem</UserName>
                    <WaitSchedule xmlns="http://www.totvs.com/">false</WaitSchedule>
                    <CadastrarDisciplinas>true</CadastrarDisciplinas>
                    <MatricPLParams z:Id="i3">
                        <AlteraMatrizContratoOriginal>false</AlteraMatrizContratoOriginal>
                        <ApagarNumeroDiario>false</ApagarNumeroDiario>
                        <ArquivoRelatorioContrato i:nil="true" />
                        <CR i:nil="true" />
                        <CadastrarContrato>false</CadastrarContrato>
                        <CancelarLancamentos>true</CancelarLancamentos>
                        <CarteiraEmitida>false</CarteiraEmitida>
                        <ChaveRMRegistroMasterTAE i:nil="true" />
                        <ClientIP i:nil="true" />
                        <CobrarDocsTipoIngressoRematriculaEB>true</CobrarDocsTipoIngressoRematriculaEB>
                        <CodColigada>1</CodColigada>
                        <CodContrato i:nil="true" />
                        <CodFilial>{$codFilial}</CodFilial>
                        <CodFormula i:nil="true" />
                        <CodInstDestino i:nil="true" />
                        <CodMotivo i:nil="true" />
                        <CodMotivoTransferencia i:nil="true" />
                        <CodPlanoPgto />
                        <CodStatus>23</CodStatus>
                        <CodStatusNovo i:nil="true" />
                        <CodStatusPendenteDisc i:nil="true" />
                        <CodStatusPendentePL i:nil="true" />
                        <CodStatusRes i:nil="true" />
                        <CodTipoCurso>{$groupToInclude['CODTIPOCURSO']}</CodTipoCurso>
                        <CodTipoMat>7</CodTipoMat>
                        <CodTurma>{$codTurma}</CodTurma>
                        <CodTurmaAnterior i:nil="true" />
                        <CodUsuario>integra.eduvem</CodUsuario>
                        <ColigadaRelatBoleto i:nil="true" />
                        <ColigadaRelatContrato i:nil="true" />
                        <ContratosCanceladosContabilizados xmlns:a="http://www.totvs.com/" />
                        <ContratosTemp xmlns:a="http://www.totvs.com/" />
                        <CopiarDescontoPorAntecipacao>false</CopiarDescontoPorAntecipacao>
                        <CopiarRespFinanceiroContrato>false</CopiarRespFinanceiroContrato>
                        <CopiarVencimentos>false</CopiarVencimentos>
                        <CotaFinal i:nil="true" />
                        <CotaInicial i:nil="true" />
                        <DadosRelatorioManifesto i:nil="true" />
                        <DataCancelamentoContrato i:nil="true" />
                        <DataCancelamentoParcelas i:nil="true" />
                        <DataFinalParc i:nil="true" />
                        <DataIngresso i:nil="true" />
                        <DataInicialParc i:nil="true" />
                        <DataMatricula>{$now}</DataMatricula>
                        <DataMatriculaAnterior i:nil="true" />
                        <DataMatriculaEncerra i:nil="true" />
                        <DataMatriculaEncerraAnterior i:nil="true" />
                        <DataMatriculaEncerraNova i:nil="true" />
                        <DataMatriculaNova i:nil="true" />
                        <DiaFixo>Nao</DiaFixo>
                        <DiaVencimento i:nil="true" />
                        <DiasVencimentoPrimeiraParcela>0</DiasVencimentoPrimeiraParcela>
                        <Disciplinas>
                        <EduMatriculaDiscParams z:Id="i4">
                            <AlunoRegular>false</AlunoRegular>
                            <ApagarNumeroDiario>false</ApagarNumeroDiario>
                            <AtendeuCreditoMinimo>false</AtendeuCreditoMinimo>
                            <CargaHoraria>4</CargaHoraria>
                            <ClientIP i:nil="true" />
                            <CobPosteriorMatric>N</CobPosteriorMatric>
                            <CodCampus i:nil="true" />
                            <CodColigada>1</CodColigada>
                            <CodCurso i:nil="true" />
                            <CodDisc>{$groupToInclude['CODDISC']}</CodDisc>
                            <CodFilial>{$codFilial}</CodFilial>
                            <CodFormula i:nil="true" />
                            <CodGrade i:nil="true" />
                            <CodHabilitacao i:nil="true" />
                            <CodItinerarioFormativo i:nil="true" />
                            <CodMotivo i:nil="true" />
                            <CodPerLet i:nil="true" />
                            <CodSituacaoMatriculaEspera>0</CodSituacaoMatriculaEspera>
                            <CodStatus>23</CodStatus>
                            <CodStatusNovo>23</CodStatusNovo>
                            <CodStatusPL>23</CodStatusPL>
                            <CodStatusRes i:nil="true" />
                            <CodSubturma />
                            <CodSubturmaMatriculado i:nil="true" />
                            <CodTipoCurso>{$groupToInclude['CODTIPOCURSO']}</CodTipoCurso>
                            <CodTurma>{$groupToInclude['CODTURMA']}</CodTurma>
                            <CodTurno>0</CodTurno>
                            <CodUsuario>integra.eduvem</CodUsuario>
                            <CoeficienteRendimeto i:nil="true" />
                            <DataMatricula>{$now}</DataMatricula>
                            <DescStatusNovo i:nil="true" />
                            <DtAlteracao i:nil="true" />
                            <EnturmandoTurmaMista>false</EnturmandoTurmaMista>
                            <ExcluirMatricula>false</ExcluirMatricula>
                            <GerarLogMatricPL>false</GerarLogMatricPL>
                            <IdHabilitacaoFilial>{$groupToInclude['IDHABILITACAOFILIAL']}</IdHabilitacaoFilial>
                            <IdHabilitacaoFilialOrigem i:nil="true" />
                            <IdHabilitacaoFilialTurmaDisc i:nil="true" />
                            <IdPerLet>{$idPerlet}</IdPerLet>
                            <IdTurmaDisc>{$groupToInclude['IDTURMADISC']}</IdTurmaDisc>
                            <IdTurmaDiscOrigem i:nil="true" />
                            <IdTurmaDiscPrincipal i:nil="true" />
                            <IdTurmaDiscSubst i:nil="true" />
                            <IncluirListaEspera>false</IncluirListaEspera>
                            <IsEnturmacao>false</IsEnturmacao>
                            <ListaTurmaMista i:nil="true" />
                            <MatriculaIsolada>false</MatriculaIsolada>
                            <MatriculaNoUltimoPeriodo>true</MatriculaNoUltimoPeriodo>
                            <MatriculaNovoPortal>false</MatriculaNovoPortal>
                            <MatriculaPortalItinerarioMenuExclusivo>false</MatriculaPortalItinerarioMenuExclusivo>
                            <MatriculaSubstituicaoAtiva>false</MatriculaSubstituicaoAtiva>
                            <MatriculaViaProcessoSeletivoRM>false</MatriculaViaProcessoSeletivoRM>
                            <MatriculaViaProcessoSeletivoTerceirizado>false</MatriculaViaProcessoSeletivoTerceirizado>
                            <MatriculaWeb>false</MatriculaWeb>
                            <MatricularDisciplinaNaListaDeEspera>false</MatricularDisciplinaNaListaDeEspera>
                            <MatrizAluno>0</MatrizAluno>
                            <MediaGlobal i:nil="true" />
                            <MudancaDeTurmaMista>false</MudancaDeTurmaMista>
                            <MudancaStatus>false</MudancaStatus>
                            <MudancaTurma>false</MudancaTurma>
                            <NomeAluno i:nil="true" />
                            <NomeCampus i:nil="true" />
                            <NomeCurso i:nil="true" />
                            <!-- <NomeDisc>Compet ncia Textual</NomeDisc> -->
                            <NomeFilial i:nil="true" />
                            <NomeHabilitacao i:nil="true" />
                            <NomeMatrizCurricular i:nil="true" />
                            <NomeTurno i:nil="true" />
                            <NumCreditos>0</NumCreditos>
                            <NumCreditosCob>0</NumCreditosCob>
                            <NumCreditosCobAnt i:nil="true" />
                            <NumDiario i:nil="true" />
                            <NumDiarioAnterior i:nil="true" />
                            <ObsHistorico i:nil="true" />
                            <OrdemPriorMatricula i:nil="true" />
                            <Origem>Produto</Origem>
                            <OrigemCriacaoMatriculaDisciplina>ProcMatricula</OrigemCriacaoMatriculaDisciplina>
                            <ParamDiversos i:nil="true" />
                            <Periodo>1</Periodo>
                            <PeriodoDeMatricula>1</PeriodoDeMatricula>
                            <PermiteAlterarDados>true</PermiteAlterarDados>
                            <PermiteTransfInternaAlunoInadimplente>false</PermiteTransfInternaAlunoInadimplente>
                            <PodeRodarNumeracaoAutomatica>true</PodeRodarNumeracaoAutomatica>
                            <PossivelFormando>false</PossivelFormando>
                            <ProcessoListaEsperaPrioridade>false</ProcessoListaEsperaPrioridade>
                            <ProcurarOutraTurma>false</ProcurarOutraTurma>
                            <RA>{$ra}</RA>
                            <RemanejarTurmaMistaNaMudancaDeTurma>false</RemanejarTurmaMistaNaMudancaDeTurma>
                            <Rematricula>false</Rematricula>
                            <SalvouMatricula>false</SalvouMatricula>
                            <TipoDiscGrade>Obrigatoria</TipoDiscGrade>
                            <TipoDisciplina>Normal</TipoDisciplina>
                            <TipoMat>7</TipoMat>
                            <TipoOperacao>Inclusao</TipoOperacao>
                            <TransferenciaInterna>false</TransferenciaInterna>
                            <UtilizaBalanceamentoTurmaSubTurmasIngressantesPS>false</UtilizaBalanceamentoTurmaSubTurmasIngressantesPS>
                            <ValidadoTurmaMista>false</ValidadoTurmaMista>
                            <ValidarInadimplencia>true</ValidarInadimplencia>
                            <ValidarIntegracaoBiblioteca>true</ValidarIntegracaoBiblioteca>
                            <ValidarRequisitosMatriculaDisciplinaItinerario>true</ValidarRequisitosMatriculaDisciplinaItinerario>
                        </EduMatriculaDiscParams>
                        </Disciplinas>
                        <DtCompetenciaFinal>  /</DtCompetenciaFinal>
                        <DtCompetenciaFinalMov>  /</DtCompetenciaFinalMov>
                        <DtCompetenciaInicial>  /</DtCompetenciaInicial>
                        <DtCompetenciaInicialMov>  /</DtCompetenciaInicialMov>
                        <DtMatriculaPag i:nil="true" />
                        <DtResultado i:nil="true" />
                        <DtSolicitacaoAlteracao i:nil="true" />
                        <EmTransacao>false</EmTransacao>
                        <GeraManifesto>false</GeraManifesto>
                        <GerarContratoAssinado>false</GerarContratoAssinado>
                        <GerarLancamento>Nao</GerarLancamento>
                        <GerarLog>true</GerarLog>
                        <GerouContratoComPlano>false</GerouContratoComPlano>
                        <IDPS>0</IDPS>
                        <IdHabilitacaoFilial>{$idHabilitacaoFilial}</IdHabilitacaoFilial>
                        <IdHabilitacaoFilialOrigem i:nil="true" />
                        <IdPerLet>{$idPerlet}</IdPerLet>
                        <IdRelatBoleto i:nil="true" />
                        <IdRelatContrato i:nil="true" />
                        <Identificador />
                        <IsDesenturmacao>false</IsDesenturmacao>
                        <IsEnturmacao>false</IsEnturmacao>
                        <IsRematricula>true</IsRematricula>
                        <ListaItinerarioFormativo i:nil="true" />
                        <MatriculaNovoPortal>false</MatriculaNovoPortal>
                        <MatriculaPortalItinerarioMenuExclusivo>false</MatriculaPortalItinerarioMenuExclusivo>
                        <MatriculaWeb>false</MatriculaWeb>
                        <MudancaStatus>false</MudancaStatus>
                        <MudancaTurma>false</MudancaTurma>
                        <NaoUsaFlexibilizacaoAlteraDataVencimento>false</NaoUsaFlexibilizacaoAlteraDataVencimento>
                        <NaoUsaFlexibilizacaoRemoverParcelasVencidas>false</NaoUsaFlexibilizacaoRemoverParcelasVencidas>
                        <NomeAluno i:nil="true" />
                        <NumCarteira />
                        <NumeroInscricao>0</NumeroInscricao>
                        <OrigemParcela i:nil="true" />
                        <ParametrosDiversos i:nil="true" />
                        <ParcelaFinal i:nil="true" />
                        <ParcelaInicial i:nil="true" />
                        <PendenteDocumento>Nao</PendenteDocumento>
                        <PendenteInadimplencia>Nao</PendenteInadimplencia>
                        <PendenteInadimplenciaBib>Nao</PendenteInadimplenciaBib>
                        <PendenteOcorrenciaBloqMatricula>Nao</PendenteOcorrenciaBloqMatricula>
                        <Periodo>1</Periodo>
                        <PermiteTransfInternaAlunoInadimplente>false</PermiteTransfInternaAlunoInadimplente>
                        <PodeRodarNumeracaoAutomatica>true</PodeRodarNumeracaoAutomatica>
                        <PossuiPendencia>false</PossuiPendencia>
                        <RA>{$ra}</RA>
                        <RematriculaEBasicoAjusteContratoHabFilial>false</RematriculaEBasicoAjusteContratoHabFilial>
                        <ResponsaveisFinanceirosContrato xmlns:a="http://www.totvs.com/" />
                        <ServicosRenovacaoRecorrencia />
                        <TextoContrato i:nil="true" />
                        <TipoOperacao>Inclusao</TipoOperacao>
                        <TipoSelecaoParcela>IdParcela</TipoSelecaoParcela>
                        <TokenAssinaturaTAE i:nil="true" />
                        <TransferenciaInterna>false</TransferenciaInterna>
                        <TurnosDiferentes>false</TurnosDiferentes>
                        <UsarPlanoPgtoParametrizacaoCurso>false</UsarPlanoPgtoParametrizacaoCurso>
                        <ValidarInadimplenciaBiblioteca>true</ValidarInadimplenciaBiblioteca>
                        <ViaCarteira />
                        <grupoRelat i:nil="true" />
                    </MatricPLParams>
                    <MatricularDisc>Sim</MatricularDisc>
                    </EduMatriculaParamsProc>
XML;

        return self::dedent($xml);
    }

    /**
     * Processo "Gerar lançamento" (EduGerarLancFromContratoSliceableData):
     * gera os lançamentos financeiros a partir do contrato.
     */
    public static function gerarLancamento(
        string|int $codColigada,
        string|int $codFilial,
        string|int $idPerlet,
        string $ra,
        string $codContrato
    ): string {
        $executionId = self::guid();
        $scheduleDateTime = date('Y-m-d\TH:i:s.0000000P');

        $xml = <<<XML
                <?xml version="1.0" encoding="utf-16"?>
                    <EduGeraLancParamsProc z:Id="i1" xmlns="http://www.totvs.com.br/RM/" xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:z="http://schemas.microsoft.com/2003/10/Serialization/">
                    <ActionModule xmlns="http://www.totvs.com/">S</ActionModule>
                    <ActionName xmlns="http://www.totvs.com/">EduGerarLancFromContrato</ActionName>
                    <CanParallelize xmlns="http://www.totvs.com/">true</CanParallelize>
                    <CanSendMail xmlns="http://www.totvs.com/">false</CanSendMail>
                    <CanWaitSchedule xmlns="http://www.totvs.com/">false</CanWaitSchedule>
                    <CodUsuario xmlns="http://www.totvs.com/">integra.eduvem</CodUsuario>
                    <ConnectionId i:nil="true" xmlns="http://www.totvs.com/" />
                    <ConnectionString i:nil="true" xmlns="http://www.totvs.com/" />
                    <Context z:Id="i2" xmlns="http://www.totvs.com/" xmlns:a="http://www.totvs.com.br/RM/">
                        <a:_params xmlns:b="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$EXERCICIOFISCAL</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODLOCPRT</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODTIPOCURSO</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">2</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$EDUTIPOUSR</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUNIDADEBIB</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODCOLIGADA</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$RHTIPOUSR</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODIGOEXTERNO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODSISTEMA</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">S</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUSUARIOSERVICO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema" />
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODUSUARIO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">integra.eduvem</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$IDPRJ</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CHAPAFUNCIONARIO</b:Key>
                            <b:Value i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">-1</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        <b:KeyValueOfanyTypeanyType>
                            <b:Key i:type="c:string" xmlns:c="http://www.w3.org/2001/XMLSchema">\$CODFILIAL</b:Key>
                            <b:Value i:type="c:int" xmlns:c="http://www.w3.org/2001/XMLSchema">{$codFilial}</b:Value>
                        </b:KeyValueOfanyTypeanyType>
                        </a:_params>
                        <a:Environment>DotNet</a:Environment>
                    </Context>
                    <CustomData i:nil="true" xmlns="http://www.totvs.com/" />
                    <DisableIsolateProcess xmlns="http://www.totvs.com/">false</DisableIsolateProcess>
                    <DriverType i:nil="true" xmlns="http://www.totvs.com/" />
                    <ExecutionId xmlns="http://www.totvs.com/">{$executionId}</ExecutionId>
                    <FailureMessage xmlns="http://www.totvs.com/">Falha na execução do processo</FailureMessage>
                    <FriendlyLogs i:nil="true" xmlns="http://www.totvs.com/" />
                    <HideProgressDialog xmlns="http://www.totvs.com/">false</HideProgressDialog>
                    <HostName xmlns="http://www.totvs.com/">integra-rm-api</HostName>
                    <Initialized xmlns="http://www.totvs.com/">true</Initialized>
                    <Ip xmlns="http://www.totvs.com/">127.0.0.1</Ip>
                    <IsolateProcess xmlns="http://www.totvs.com/">false</IsolateProcess>
                    <JobID xmlns="http://www.totvs.com/">
                        <Children />
                        <ExecID>1</ExecID>
                        <ID>900807</ID>
                        <IsPriorityJob>false</IsPriorityJob>
                    </JobID>
                    <JobServerHostName xmlns="http://www.totvs.com/">114384-elastic-service-N-RM-P-CAQB4Z-3-d84cWIN-Z2</JobServerHostName>
                    <MasterActionName xmlns="http://www.totvs.com/">EduContratoAction</MasterActionName>
                    <MaximumQuantityOfPrimaryKeysPerProcess xmlns="http://www.totvs.com/">1000</MaximumQuantityOfPrimaryKeysPerProcess>
                    <MinimumQuantityOfPrimaryKeysPerProcess xmlns="http://www.totvs.com/">1</MinimumQuantityOfPrimaryKeysPerProcess>
                    <NetworkUser xmlns="http://www.totvs.com/">integra.eduvem</NetworkUser>
                    <NotifyEmail xmlns="http://www.totvs.com/">false</NotifyEmail>
                    <NotifyEmailList i:nil="true" xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                    <NotifyFluig xmlns="http://www.totvs.com/">false</NotifyFluig>
                    <OnlineMode xmlns="http://www.totvs.com/">false</OnlineMode>
                    <PrimaryKeyList xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                        <a:ArrayOfanyType>
                        <a:anyType i:type="b:short" xmlns:b="http://www.w3.org/2001/XMLSchema">{$codColigada}</a:anyType>
                        <a:anyType i:type="b:string" xmlns:b="http://www.w3.org/2001/XMLSchema">{$ra}</a:anyType>
                        <a:anyType i:type="b:int" xmlns:b="http://www.w3.org/2001/XMLSchema">{$idPerlet}</a:anyType>
                        <a:anyType i:type="b:string" xmlns:b="http://www.w3.org/2001/XMLSchema">{$codContrato}</a:anyType>
                        </a:ArrayOfanyType>
                    </PrimaryKeyList>
                    <PrimaryKeyNames xmlns="http://www.totvs.com/" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                        <a:string>CODCOLIGADA</a:string>
                        <a:string>RA</a:string>
                        <a:string>IDPERLET</a:string>
                        <a:string>CODCONTRATO</a:string>
                    </PrimaryKeyNames>
                    <PrimaryKeyTableName xmlns="http://www.totvs.com/">SCONTRATO</PrimaryKeyTableName>
                    <ProcessName xmlns="http://www.totvs.com/">Gerar lançamento</ProcessName>
                    <QuantityOfSplits xmlns="http://www.totvs.com/">0</QuantityOfSplits>
                    <SaveLogInDatabase xmlns="http://www.totvs.com/">true</SaveLogInDatabase>
                    <SaveParamsExecution xmlns="http://www.totvs.com/">false</SaveParamsExecution>
                    <ScheduleDateTime xmlns="http://www.totvs.com/">{$scheduleDateTime}</ScheduleDateTime>
                    <Scheduler xmlns="http://www.totvs.com/">JobMonitor</Scheduler>
                    <SendMail xmlns="http://www.totvs.com/">false</SendMail>
                    <ServerName xmlns="http://www.totvs.com/">EduGerarLancFromContratoSliceableData</ServerName>
                    <ServiceInterface i:type="b:RuntimeType" z:FactoryType="c:UnitySerializationHolder" xmlns="http://www.totvs.com/" xmlns:a="http://schemas.datacontract.org/2004/07/System" xmlns:b="-mscorlib, Version=4.0.0.0, Culture=neutral, PublicKeyToken=b77a5c561934e089-System-System.RuntimeType" xmlns:c="-mscorlib, Version=4.0.0.0, Culture=neutral, PublicKeyToken=b77a5c561934e089-System-System.UnitySerializationHolder">
                        <Data i:type="d:string" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">RM.Lib.IRMSSliceableProcess`1[[RM.Edu.Financeiro.Lancamento.EduGeraLancSliceableParamsProc, RM.Edu.Financeiro.Intf, Version=12.1.2502.117, Culture=neutral, PublicKeyToken=null]]</Data>
                        <UnityType i:type="d:int" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">4</UnityType>
                        <AssemblyName i:type="d:string" xmlns="" xmlns:d="http://www.w3.org/2001/XMLSchema">RM.Lib, Version=12.1.2502.1, Culture=neutral, PublicKeyToken=null</AssemblyName>
                    </ServiceInterface>
                    <ShouldParallelize xmlns="http://www.totvs.com/">false</ShouldParallelize>
                    <ShowReExecuteButton xmlns="http://www.totvs.com/">true</ShowReExecuteButton>
                    <StatusMessage i:nil="true" xmlns="http://www.totvs.com/" />
                    <SuccessMessage xmlns="http://www.totvs.com/">Processo executado com sucesso</SuccessMessage>
                    <SyncExecution xmlns="http://www.totvs.com/">false</SyncExecution>
                    <UseJobMonitor xmlns="http://www.totvs.com/">true</UseJobMonitor>
                    <UserName xmlns="http://www.totvs.com/">integra.eduvem</UserName>
                    <WaitSchedule xmlns="http://www.totvs.com/">false</WaitSchedule>
                    <EnableJobErrorProgressbar xmlns="http://www.totvs.com/">false</EnableJobErrorProgressbar>
                    <EnableTracing xmlns="http://www.totvs.com/">false</EnableTracing>
                    <LocalOnlyExecutor xmlns="http://www.totvs.com/">RMSJobData</LocalOnlyExecutor>
                    <RMSJobIds i:nil="true" xmlns="http://www.totvs.com/" />
                    <SlicesCount xmlns="http://www.totvs.com/">0</SlicesCount>
                    <CodColigada>0</CodColigada>
                    <CodFilial>0</CodFilial>
                    <CodTipoCurso>0</CodTipoCurso>
                    <ClausulasFiltroRa i:nil="true" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                    <CodCurso i:nil="true" />
                    <CodGrade i:nil="true" />
                    <CodHabilitacao i:nil="true" />
                    <CodSentencaSql i:nil="true" />
                    <CodTurma i:nil="true" />
                    <CodTurno>0</CodTurno>
                    <IsExcluirPrevia>false</IsExcluirPrevia>
                    <IsProcGeraPrevia>false</IsProcGeraPrevia>
                    <ParamsGeraLanc z:Id="i3">
                        <AgrupaBolentroDeOutrasFiliais i:nil="true" />
                        <AgrupamentoBoleto i:nil="true" />
                        <AtualizarLancamentoGerado i:nil="true" />
                        <BoletoPorServico i:nil="true" />
                        <CampoAlfaOp1 i:nil="true" />
                        <CampoAlfaOp2 i:nil="true" />
                        <CampoAlfaOp3 i:nil="true" />
                        <CnabCarteira i:nil="true" />
                        <CodCCusto i:nil="true" />
                        <CodCfo i:nil="true" />
                        <CodColCfo>0</CodColCfo>
                        <CodColCxa i:nil="true" />
                        <CodColCxaAPagar i:nil="true" />
                        <CodColNatFinanceira i:nil="true" />
                        <CodColigada>0</CodColigada>
                        <CodColigadaConta>0</CodColigadaConta>
                        <CodContrato i:nil="true" />
                        <CodCxa i:nil="true" />
                        <CodCxaAPagar i:nil="true" />
                        <CodDepto i:nil="true" />
                        <CodEvento i:nil="true" />
                        <CodFilial>0</CodFilial>
                        <CodMoeda i:nil="true" />
                        <CodNatFinanceira i:nil="true" />
                        <CodPlanoPgto i:nil="true" />
                        <CodStatusMatriculaDisc>0</CodStatusMatriculaDisc>
                        <CodTabOp1 i:nil="true" />
                        <CodTabOp2 i:nil="true" />
                        <CodTabOp3 i:nil="true" />
                        <CodTabOp4 i:nil="true" />
                        <CodTabOp5 i:nil="true" />
                        <CodTipoCurso>0</CodTipoCurso>
                        <CodTipoDocumento i:nil="true" />
                        <CodTipoDocumentoAPagar i:nil="true" />
                        <CodUsuario i:nil="true" />
                        <ConsideraDescAntecipacao>S</ConsideraDescAntecipacao>
                        <ConsideraDescAntecipacaoBolsa>N</ConsideraDescAntecipacaoBolsa>
                        <ContratoHabNull i:nil="true" />
                        <CotaFinal i:nil="true" />
                        <CotaInicial i:nil="true" />
                        <DataCompetencia i:nil="true" />
                        <DataCompetenciaFinal i:nil="true" />
                        <DataCompetenciaInicial i:nil="true" />
                        <DataFinal i:nil="true" />
                        <DataInicial i:nil="true" />
                        <DataOp1 i:nil="true" />
                        <DataOp2 i:nil="true" />
                        <DataOp3 i:nil="true" />
                        <DataOp4 i:nil="true" />
                        <DataOp5 i:nil="true" />
                        <DataVencimento>0001-01-01T00:00:00</DataVencimento>
                        <FiltraPorIdParcela>false</FiltraPorIdParcela>
                        <Historico i:nil="true" />
                        <IncluirExcluirBolsaRetroativa>Inclusao</IncluirExcluirBolsaRetroativa>
                        <IsBolsaRetroativaModAntigo_NaoReGerarBoleto>false</IsBolsaRetroativaModAntigo_NaoReGerarBoleto>
                        <IsGeracaoPreviaLancamento>false</IsGeracaoPreviaLancamento>
                        <IsIncluirExcluirBolsaRetroativa>false</IsIncluirExcluirBolsaRetroativa>
                        <IsPlanoPagamentoDefault>false</IsPlanoPagamentoDefault>
                        <IsSimulaLancamento>false</IsSimulaLancamento>
                        <IsSomenteContratosAtivos>true</IsSomenteContratosAtivos>
                        <ListaAlunos xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaContaCorrente />
                        <ListaHabilitacaoFilial xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaIdParcela xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaIdTurmaDisc xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaPeriodoLetivo xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaPlanosPgto xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaServicos xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaSitMatricula xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                        <ListaTipoContrato xmlns:a="http://schemas.datacontract.org/2004/07/RM.Edu.Consts" />
                        <ListaTipoParcela xmlns:a="http://schemas.datacontract.org/2004/07/RM.Edu.Consts" />
                        <MatriculaOnlineContextoParams i:nil="true" />
                        <NaoUsaFlexibilizacaoAlteraDataVencimento>false</NaoUsaFlexibilizacaoAlteraDataVencimento>
                        <NaoUsaFlexibilizacaoRemoverParcelasVencidas>false</NaoUsaFlexibilizacaoRemoverParcelasVencidas>
                        <NroConta i:nil="true" />
                        <NumAgencia i:nil="true" />
                        <NumAgenciaAPagar i:nil="true" />
                        <NumBanco i:nil="true" />
                        <NumBancoAPagar i:nil="true" />
                        <OperacaoBolsaRetroativa i:nil="true" />
                        <OrigemParcela i:nil="true" />
                        <OrigemSimulacaoIsBolsaRetroativa>false</OrigemSimulacaoIsBolsaRetroativa>
                        <ParcelaFinal i:nil="true" />
                        <ParcelaInicial i:nil="true" />
                        <PermiteAtualizarContaCorrente>true</PermiteAtualizarContaCorrente>
                        <PersonalizacaoListaSituacaoMatricula>Nao</PersonalizacaoListaSituacaoMatricula>
                        <ProcessaDescAntecipacaoDuranteGerPrevia>Nao</ProcessaDescAntecipacaoDuranteGerPrevia>
                        <TipoBolsaContrato>S</TipoBolsaContrato>
                        <TipoCalculoPorCredito>Default</TipoCalculoPorCredito>
                        <TipoCob i:nil="true" />
                        <TipoContaCaixa i:nil="true" />
                        <TipoContabilLan i:nil="true" />
                        <TipoContabilLanAPagar i:nil="true" />
                        <TipoSelecaoParcela>IdParcela</TipoSelecaoParcela>
                        <ValorBolsaRetroativa>0</ValorBolsaRetroativa>
                        <ValorOriginal>0</ValorOriginal>
                    </ParamsGeraLanc>
                    <RA i:nil="true" />
                    <SalvarParams>false</SalvarParams>
                    <TipoFiltro>Nenhum</TipoFiltro>
                    <ValorParamsConsultaSql i:nil="true" xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays" />
                    </EduGeraLancParamsProc>
XML;

        return self::dedent($xml);
    }

    /**
     * Preenche o template canônico do FinLanBaixaParamsProc (obtido do próprio
     * RM via GetSchema) com os dados de uma baixa, e devolve o strXmlParams.
     *
     * Por que assim: o contrato do processo é enorme e cheio de campos/enums
     * obrigatórios. Reproduzi-lo à mão gera itens que não desserializam (e o RM
     * responde "Os lançamentos devem ser informados"). Partindo do template do
     * próprio RM, garantimos a estrutura exata — só trocamos os valores dinâmicos
     * e esvaziamos as listas de saída/placeholder.
     *
     * @param string $template  XML devolvido por GetSchema('FinLanBaixaData')
     * @param array<string,string> $d  IDLAN, VALOR, CODCOLIGADA, CODFILIAL, CODCXA,
     *        DATABAIXA (Y-m-d), TIPOFORMAPAGTO, HISTORICO, USUARIO, TIPOBAIXA, CHAPA
     */
    public static function preencherBaixa(string $template, array $d): string
    {
        $idLan   = (string) ($d['IDLAN'] ?? '0');
        $valor   = (string) ($d['VALOR'] ?? '0');
        $col     = (string) ($d['CODCOLIGADA'] ?? '1');
        $fil     = (string) ($d['CODFILIAL'] ?? '1');
        $cxa     = (string) ($d['CODCXA'] ?? '');
        $data    = ((string) ($d['DATABAIXA'] ?? date('Y-m-d'))) . 'T00:00:00-03:00';
        $forma   = (string) ($d['TIPOFORMAPAGTO'] ?? 'Dinheiro');
        $hist    = (string) ($d['HISTORICO'] ?? '');
        $usuario = (string) ($d['USUARIO'] ?? '');
        $tipoBx  = (string) ($d['TIPOBAIXA'] ?? 'Simplificada');
        $chapa   = (string) ($d['CHAPA'] ?? '-1');

        // DOMDocument não engole a declaração utf-16 com bytes utf-8: troca p/ utf-8
        // ao carregar e restaura utf-16 (o que o RM espera) ao devolver.
        $xmlIn = preg_replace('/encoding="utf-16"/i', 'encoding="utf-8"', $template, 1);

        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        if (!@$doc->loadXML($xmlIn)) {
            throw new \RuntimeException('Template de baixa (GetSchema) não é um XML válido.');
        }
        $root = $doc->documentElement;

        // ---- raiz (campos escalares da baixa) ----
        self::setFilho($root, 'CodColigada', $col);
        self::setFilho($root, 'CotacaoBaixa', '1');
        self::setFilho($root, 'DataBaixa', $data);
        self::setFilho($root, 'DataSistema', $data);
        self::setFilho($root, 'HistoricoBaixa', $hist);
        self::setFilho($root, 'CodColCxa', $col);
        self::setFilho($root, 'CodCxa', $cxa);
        self::setFilho($root, 'Usuario', $usuario);
        self::setFilho($root, 'TipoBaixa', $tipoBx);

        // ---- Context: params por chave ----
        if ($ctx = self::filho($root, 'Context')) {
            if ($params = self::filho($ctx, '_params')) {
                $map = ['$CODCOLIGADA' => $col, '$CODFILIAL' => $fil, '$CHAPAFUNCIONARIO' => $chapa, '$CODUSUARIO' => $usuario];
                foreach ($params->childNodes as $kv) {
                    if (!$kv instanceof \DOMElement) {
                        continue;
                    }
                    $chave = self::filho($kv, 'Key');
                    $val   = self::filho($kv, 'Value');
                    if ($chave && $val && array_key_exists(trim($chave->textContent), $map)) {
                        self::apenasTexto($val, $map[trim($chave->textContent)]);
                    }
                }
            }
        }

        // ---- PrimaryKeyList/Names: placeholder do schema -> esvazia ----
        self::esvaziar(self::filho($root, 'PrimaryKeyList'));
        self::esvaziar(self::filho($root, 'PrimaryKeyNames'));

        // ---- FormasPagamento > FinFormaPagtoBaixaParamsProc ----
        if (($fp = self::filho($root, 'FormasPagamento')) && ($fpi = self::primeiroElemento($fp))) {
            self::setFilho($fpi, 'CodColCxa', $col);
            self::setFilho($fpi, 'CodColProp', $col);
            self::setFilho($fpi, 'CodColigada', $col);
            self::setFilho($fpi, 'CodCxa', $cxa);
            self::setFilho($fpi, 'CodFilial', $fil);
            self::setFilho($fpi, 'DescFormaPagto', $forma);
            self::setFilho($fpi, 'TipoFormaPagto', $forma);
            self::setFilho($fpi, 'Valor', $valor);
            // sub-objetos complexos (DataSets) -> esvazia p/ não mandar lixo
            foreach (['Cartao', 'Cheque', 'Mutuo', 'Contabilizacao'] as $sub) {
                self::esvaziar(self::filho($fpi, $sub));
            }
        }

        // ---- ItensBaixa > FinItemBaixa (escalares; nested já vem vazio) ----
        if (($ib = self::filho($root, 'ItensBaixa')) && ($item = self::primeiroElemento($ib))) {
            self::setFilho($item, 'CodColCxa', $col);
            self::setFilho($item, 'CodColigada', $col);
            self::setFilho($item, 'CodCxa', $cxa);
            self::setFilho($item, 'CodFilial', $fil);
            self::setFilho($item, 'DataBaixa', $data);
            self::setFilho($item, 'IdLan', $idLan);
            self::setFilho($item, 'ValorBaixa', $valor);
            self::setFilho($item, 'ValorOriginal', $valor);
            self::setFilho($item, 'Usuario', $usuario);
            self::setFilho($item, 'CotacaoBaixa', '1');
            self::setFilho($item, 'TipoFormaPagto', $forma);
            self::setFilho($item, 'PagRec', 'Receber');
        }

        // ---- Lancamentos > FinLancamentoBaixaResult (escalares; esvazia nested) ----
        if (($lc = self::filho($root, 'Lancamentos')) && ($res = self::primeiroElemento($lc))) {
            self::setFilho($res, 'Coligada', $col);
            self::setFilho($res, 'ID', $idLan);
            self::setFilho($res, 'CodColigada', $col);
            self::setFilho($res, 'IdLan', $idLan);
            self::setFilho($res, 'CodFilial', $fil);
            self::setFilho($res, 'ValorLiquido', $valor);
            self::setFilho($res, 'ValorOriginal', $valor);
            self::setFilho($res, 'ValorOriginalIndexado', $valor);
            self::setFilho($res, 'ValordaBaixadoLancto', $valor);
            // listas internas com o "monstro" contábil/rateio -> RM recomputa
            foreach (['ListaDeItemBaixa', 'ListTributos', 'ListaVaLorIntegracaoLancamento', 'RatCCusto', 'RatDepto'] as $l) {
                self::esvaziar(self::filho($res, $l));
            }
        }

        // ---- PagtoPorLan > FinPagtoLanParamsProc ----
        if (($pl = self::filho($root, 'PagtoPorLan')) && ($pli = self::primeiroElemento($pl))) {
            self::setFilho($pli, 'IdLan', $idLan);
            self::setFilho($pli, 'Valor', $valor);
        }

        // ---- listas de saída/placeholder -> esvazia ----
        foreach ([
            'ListBaixaGeradas', 'ListContabilLan', 'ListaBaixas', 'ListaIdBoletoBaixa',
            'RetencoesAcumuladas', 'RetencoesAcumuladasRateio', 'TransacoesSiTef',
        ] as $l) {
            self::esvaziar(self::filho($root, $l));
        }

        $out = $doc->saveXML();
        // restaura a declaração utf-16 esperada pelo RM
        return preg_replace('/encoding="utf-8"/i', 'encoding="utf-16"', $out, 1);
    }

    /** Primeiro filho DOMElement com o localName dado (namespace-agnóstico). */
    private static function filho(\DOMElement $pai, string $local): ?\DOMElement
    {
        foreach ($pai->childNodes as $n) {
            if ($n instanceof \DOMElement && $n->localName === $local) {
                return $n;
            }
        }
        return null;
    }

    /** Primeiro filho DOMElement qualquer (o item de uma lista). */
    private static function primeiroElemento(\DOMElement $pai): ?\DOMElement
    {
        foreach ($pai->childNodes as $n) {
            if ($n instanceof \DOMElement) {
                return $n;
            }
        }
        return null;
    }

    /** Define o texto de um filho (por localName), preservando atributos (i:type). */
    private static function setFilho(\DOMElement $pai, string $local, string $valor): void
    {
        $el = self::filho($pai, $local);
        if ($el !== null) {
            self::apenasTexto($el, $valor);
        }
    }

    /** Substitui o conteúdo do elemento por um único nó de texto; remove i:nil. */
    private static function apenasTexto(\DOMElement $el, string $valor): void
    {
        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }
        if ($el->hasAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil')) {
            $el->removeAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil');
        }
        $el->appendChild($el->ownerDocument->createTextNode($valor));
    }

    /** Remove todos os filhos-elemento (esvazia uma lista/objeto). */
    private static function esvaziar(?\DOMElement $el): void
    {
        if ($el === null) {
            return;
        }
        $filhos = [];
        foreach ($el->childNodes as $n) {
            if ($n instanceof \DOMElement) {
                $filhos[] = $n;
            }
        }
        foreach ($filhos as $f) {
            $el->removeChild($f);
        }
    }
}
