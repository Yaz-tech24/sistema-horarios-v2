<?php
/* ============================================================
   Geração automática de horários.

   Como funciona (por turma):
   1. Blocos livres = os blocos do turno da turma que não têm aulas
      marcadas à mão nem Atividades (a Pastoral, RN08, é garantida antes).
      O Estudo Autónomo é só preenchimento: é apagado e refeito no fim — é
      isso que permite voltar a gerar depois de corrigir uma disciplina.
   2. Para cada disciplina calcula-se em que blocos pode ficar: o docente
      regente (e o assistente) livres nesse bloco em TODOS os cursos (RN01)
      e uma sala livre (RN02) — o laboratório da disciplina (RN16) ou a sala
      fixa escolhida no formulário são obrigatórios; senão, a primeira sala
      livre de salasCandidatas().
   3. Procura completa (com retrocesso) das combinações de blocos: as
      sessões de uma disciplina ficam em dias diferentes e não seguidos
      (RN09) e cada bloco só tem uma aula. Coloca primeiro a disciplina com
      menos alternativas e, entre as soluções com o maior número de sessões
      colocadas, fica com a mais equilibrada (aulas espalhadas pela semana,
      sem buracos a meio do dia, docentes sem mais de 3 aulas no mesmo dia,
      sessões de uma disciplina bem espaçadas).
   4. Cada aula escolhida passa ainda por verificarConflitos() antes de ser
      gravada — o motor de conflitos continua a ser a única autoridade.
   5. O que não ficou colocado vai para o resumo com o MOTIVO.

   O antigo algoritmo colocava as disciplinas por ordem alfabética, no
   primeiro bloco que servisse, sem nunca voltar atrás: com o turno cheio,
   ou com um docente com pouca disponibilidade, deixava disciplinas por
   agendar mesmo quando havia uma arrumação com todas.
   ============================================================ */

const GERADOR_LIMITE_NOS = 80000;      // nós da procura, por turma
const GERADOR_SEGUNDOS_TURMA = 3.0;    // tempo máximo de procura, por turma
const GERADOR_SEGUNDOS_CURSO = 25.0;   // tempo total ao gerar um curso inteiro
const GERADOR_MAX_AULAS_DOCENTE_DIA = 3;

const NOMES_DIAS = ['Segunda' => 'Segunda', 'Terca' => 'Terça', 'Quarta' => 'Quarta', 'Quinta' => 'Quinta', 'Sexta' => 'Sexta'];

/* ---------------- dados ---------------- */

/* Ocupação de docentes e salas em todas as aulas de horários não
   arquivados: ['docentes' => [id][dia] => [[hi,hf],...], 'salas' => idem]. */
function carregarOcupacao(PDO $pdo): array {
    $ocup = ['docentes' => [], 'salas' => []];
    $rows = $pdo->query(
        "SELECT a.docente_id, a.docente_regente_id, a.docente_assistente_id, a.sala_id, a.dia_semana,
                LEFT(a.hora_inicio, 5) AS hi, LEFT(a.hora_fim, 5) AS hf
         FROM aulas a JOIN horarios h ON h.id = a.horario_id
         WHERE h.estado != 'Arquivado' AND a.tipo_bloco = 'Aula'")->fetchAll();
    foreach ($rows as $r) {
        $docs = array_unique(array_filter([(int)($r['docente_regente_id'] ?: $r['docente_id']), (int)$r['docente_assistente_id']]));
        foreach ($docs as $d) { $ocup['docentes'][$d][$r['dia_semana']][] = [$r['hi'], $r['hf']]; }
        if ($r['sala_id']) { $ocup['salas'][(int)$r['sala_id']][$r['dia_semana']][] = [$r['hi'], $r['hf']]; }
    }
    return $ocup;
}

function intervaloOcupado(array $intervalos, string $hi, string $hf): bool {
    foreach ($intervalos as [$a, $b]) { if ($a < $hf && $b > $hi) { return true; } }
    return false;
}

/* Apaga o Estudo Autónomo de um horário (é preenchimento, refeito no fim). */
function apagarEstudoAutonomo(PDO $pdo, int $horarioId): void {
    $stmt = $pdo->prepare("SELECT id FROM aulas WHERE horario_id = ? AND tipo_bloco = 'Estudo_Autonomo'");
    $stmt->execute([$horarioId]);
    removerBlocos($pdo, $horarioId, array_column($stmt->fetchAll(), 'id'));
}

/* Tudo o que a procura precisa de saber sobre uma turma. */
function prepararProblema(PDO $pdo, array $horario, array $turma, array $curso, ?int $salaForcada, array $salas): array {
    $blocos = blocosDoTurno($turma['turno']);
    $diaIdx = array_flip(DIAS_SEMANA);

    $stmt = $pdo->prepare(
        "SELECT tipo_bloco, disciplina_id, dia_semana, LEFT(hora_inicio,5) AS hi, LEFT(hora_fim,5) AS hf
         FROM aulas WHERE horario_id = ?");
    $stmt->execute([$horario['id']]);
    $existentes = $stmt->fetchAll();

    $ocupado = []; $contaDia = array_fill(0, count(DIAS_SEMANA), 0); $usoBloco = [];
    $sessoesExistentes = [];
    foreach ($existentes as $e) {
        $ocupado[$e['dia_semana'] . '|' . $e['hi']] = true;
        $di = $diaIdx[$e['dia_semana']] ?? null;
        if ($di === null) { continue; }
        foreach ($blocos as $bi => [$hi]) { if ($hi === $e['hi']) { $usoBloco[$di][$bi] = 1; } }
        if ($e['tipo_bloco'] === 'Aula') {
            $contaDia[$di]++;
            if ($e['disciplina_id']) { $sessoesExistentes[(int)$e['disciplina_id']][] = $di; }
        }
    }

    $slots = [];
    foreach (DIAS_SEMANA as $di => $d) {
        foreach ($blocos as $bi => [$hi, $hf]) {
            if (!isset($ocupado["$d|$hi"])) { $slots[] = ['dia' => $d, 'di' => $di, 'bi' => $bi, 'hi' => $hi, 'hf' => $hf]; }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM disciplinas WHERE curso_id = ? AND ano_curricular = ? ORDER BY nome");
    $stmt->execute([$curso['id'], $turma['ano_curricular']]);
    $disciplinas = []; $semRegente = []; $idsDocentes = [];
    foreach ($stmt->fetchAll() as $d) {
        $carga = max(1, min(2, (int)$d['carga_horaria']));
        $dias = $sessoesExistentes[(int)$d['id']] ?? [];
        $k = $carga - count($dias);
        if ($k <= 0) { continue; }
        if (empty($d['docente_regente_id'])) { $semRegente[] = $d['nome']; continue; }
        $docs = array_values(array_unique(array_filter([(int)$d['docente_regente_id'], (int)$d['docente_assistente_id']])));
        $idsDocentes = array_merge($idsDocentes, $docs);
        $disciplinas[] = [
            'id' => (int)$d['id'], 'nome' => $d['nome'], 'k' => $k, 'carga' => $carga,
            'diasExistentes' => $dias, 'docentes' => $docs,
            'regente' => (int)$d['docente_regente_id'],
            'assistente' => $d['docente_assistente_id'] ? (int)$d['docente_assistente_id'] : null,
            'salas' => salasCandidatas($salas, $turma, $d, $salaForcada),
            'lab' => !$salaForcada && !empty($d['sala_id']) ? (int)$d['sala_id'] : null,
            'forcada' => $salaForcada,
        ];
    }

    $nomesDocentes = [];
    if ($idsDocentes) {
        $ids = array_values(array_unique($idsDocentes));
        $stmt = $pdo->prepare("SELECT id, nome FROM docentes WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $r) { $nomesDocentes[(int)$r['id']] = $r['nome']; }
    }
    $nomesSalas = [];
    foreach ($salas as $s) { $nomesSalas[(int)$s['id']] = $s['nome']; }

    return [
        'horario' => $horario, 'turma' => $turma, 'slots' => $slots, 'disciplinas' => $disciplinas,
        'semRegente' => $semRegente, 'contaDia' => $contaDia, 'usoBloco' => $usoBloco,
        'nomesDocentes' => $nomesDocentes, 'nomesSalas' => $nomesSalas,
    ];
}

/* ---------------- procura ---------------- */

final class ProcuraHorarioTurma
{
    private array $usado = [];
    private array $escolha = [];
    private int $sessoes = 0;
    private int $pen = 0;
    private ?array $melhor = null;
    private int $nos = 0;
    private bool $parar = false;
    private array $livresDia = [];   // blocos livres ainda por ocupar, por dia
    private int $maximoPossivel = 0;
    private int $penOtima = 0;       // custo mínimo teórico (distribuição perfeita)

    /* $disc[i] = ['k', 'cheias' => [[slots[], pen]], 'parciais' => [[slots[], pen]], 'docentes' => ids]
       $slots[s] = ['di', 'bi'] */
    public function __construct(
        private array $disc, private array $slots, private array $contaDia,
        private array $usoBloco, private array $cargaDoc, private float $prazo, private int $nBlocos
    ) {
        foreach ($slots as $sl) { $this->livresDia[$sl['di']] = ($this->livresDia[$sl['di']] ?? 0) + 1; }
        $this->maximoPossivel = maximoAlcancavel($disc, $nBlocos);
        $this->penOtima = $this->custoMinimoEspalhar($this->maximoPossivel);
    }

    public function resolver(): array {
        $this->procurar(array_keys($this->disc));
        return $this->melhor ?? ['sessoes' => 0, 'pen' => 0, 'escolha' => []];
    }

    /* Custo mínimo de pôr mais $n aulas, pondo cada uma no dia com menos
       aulas que ainda tenha blocos livres — ignora docentes e RN09, por isso
       nenhuma arrumação real fica abaixo disto (limite inferior). */
    private function custoMinimoEspalhar(int $n): int {
        $conta = $this->contaDia; $livres = $this->livresDia; $custo = 0;
        for ($j = 0; $j < $n; $j++) {
            $dia = null;
            foreach ($livres as $di => $l) { if ($l > 0 && ($dia === null || $conta[$di] < $conta[$dia])) { $dia = $di; } }
            if ($dia === null) { break; }
            $custo += 2 * $conta[$dia] + 1;
            $conta[$dia]++; $livres[$dia]--;
        }
        return $custo;
    }

    private function custoSlot(int $s, array $docentes): int {
        $di = $this->slots[$s]['di'];
        $c = 2 * $this->contaDia[$di] + 1;                       // espalhar pela semana
        foreach ($docentes as $t) {                               // docente sobrecarregado nesse dia
            if (($this->cargaDoc[$t][$di] ?? 0) + 1 > GERADOR_MAX_AULAS_DOCENTE_DIA) { $c += 4; }
        }
        return $c;
    }

    private function aplicar(int $i, array $opcao): int {
        $delta = $opcao[1];
        foreach ($opcao[0] as $s) {
            $delta += $this->custoSlot($s, $this->disc[$i]['docentes']);
            $di = $this->slots[$s]['di'];
            $this->usado[$s] = true;
            $this->contaDia[$di]++;
            $this->livresDia[$di]--;
            $this->usoBloco[$di][$this->slots[$s]['bi']] = ($this->usoBloco[$di][$this->slots[$s]['bi']] ?? 0) + 1;
            foreach ($this->disc[$i]['docentes'] as $t) { $this->cargaDoc[$t][$di] = ($this->cargaDoc[$t][$di] ?? 0) + 1; }
        }
        $this->escolha[$i] = $opcao[0];
        $this->sessoes += count($opcao[0]);
        $this->pen += $delta;
        return $delta;
    }

    private function desfazer(int $i, array $opcao, int $delta): void {
        foreach ($opcao[0] as $s) {
            $di = $this->slots[$s]['di'];
            unset($this->usado[$s]);
            $this->contaDia[$di]--;
            $this->livresDia[$di]++;
            $bi = $this->slots[$s]['bi'];
            if (--$this->usoBloco[$di][$bi] <= 0) { unset($this->usoBloco[$di][$bi]); }
            foreach ($this->disc[$i]['docentes'] as $t) { $this->cargaDoc[$t][$di]--; }
        }
        unset($this->escolha[$i]);
        $this->sessoes -= count($opcao[0]);
        $this->pen -= $delta;
    }

    /* Buracos a meio do dia (aula, livre, aula) — só se conhecem no fim. */
    private function penBuracos(): int {
        $p = 0;
        foreach ($this->usoBloco as $usados) {
            $usados = array_keys(array_filter($usados));
            if (count($usados) < 2) { continue; }
            $p += 2 * ((max($usados) - min($usados) + 1) - count($usados));
        }
        return $p;
    }

    /* +2 se pôr uma aula neste bloco abre um buraco no dia (ex.: 1º e 3º
       blocos ocupados, o do meio livre); -2 se tapa um; 0 caso contrário. */
    private function efeitoBuraco(int $s): int {
        $di = $this->slots[$s]['di']; $bi = $this->slots[$s]['bi'];
        $usados = array_keys(array_filter($this->usoBloco[$di] ?? []));
        if (!$usados) { return 0; }
        $antes = (max($usados) - min($usados) + 1) - count($usados);
        $usados[] = $bi;
        $depois = (max($usados) - min($usados) + 1) - count($usados);
        return 2 * ($depois - $antes);
    }

    private function compativel(array $opcao): bool {
        foreach ($opcao[0] as $s) { if (isset($this->usado[$s])) { return false; } }
        return true;
    }

    private function procurar(array $porFazer): void {
        $this->nos++;
        if ($this->nos > GERADOR_LIMITE_NOS || ($this->nos % 128 === 0 && microtime(true) > $this->prazo)) {
            $this->parar = true;
        }

        if (!$porFazer) {
            $total = $this->pen + $this->penBuracos();
            if ($this->melhor === null || $this->sessoes > $this->melhor['sessoes']
                || ($this->sessoes === $this->melhor['sessoes'] && $total < $this->melhor['pen'])) {
                $this->melhor = ['sessoes' => $this->sessoes, 'pen' => $total, 'escolha' => $this->escolha];
                // Todas as sessões possíveis, com a distribuição perfeita:
                // não há melhor — pára já.
                if ($this->sessoes === $this->maximoPossivel && $total <= $this->penOtima) { $this->parar = true; }
            }
            return;
        }

        // Limite: mesmo pondo todas as sessões que faltam, não se ultrapassa
        // a melhor solução já encontrada → não vale a pena continuar.
        $faltam = 0;
        foreach ($porFazer as $i) { $faltam += $this->disc[$i]['max']; }
        $maximo = $this->sessoes + min($faltam, $this->nBlocos - count($this->usado));
        if ($this->melhor !== null) {
            if ($maximo < $this->melhor['sessoes']) { return; }
            if ($maximo === $this->melhor['sessoes']
                && $this->pen + $this->custoMinimoEspalhar($maximo - $this->sessoes) >= $this->melhor['pen']) { return; }
        }

        // A disciplina com menos alternativas primeiro.
        $escolhida = null; $menos = PHP_INT_MAX; $cheias = [];
        foreach ($porFazer as $i) {
            $comp = array_values(array_filter($this->disc[$i]['cheias'], fn($o) => $this->compativel($o)));
            $n = count($comp);
            if ($n < $menos || ($n === $menos && $this->disc[$i]['k'] > $this->disc[$escolhida]['k'])) {
                $escolhida = $i; $menos = $n; $cheias = $comp;
                if ($n === 0) { break; }
            }
        }
        $i = $escolhida;
        $docs = $this->disc[$i]['docentes'];
        // Ordem das tentativas: as mais baratas primeiro — e, só para
        // ordenar, preferir blocos encostados aos já ocupados nesse dia (as
        // primeiras soluções encontradas já saem compactas, sem buracos).
        $custo = function (array $o) use ($docs): int {
            $c = $o[1];
            foreach ($o[0] as $s) { $c += $this->custoSlot($s, $docs) + $this->efeitoBuraco($s); }
            return $c;
        };
        usort($cheias, fn($a, $b) => $custo($a) <=> $custo($b));
        $parciais = array_values(array_filter($this->disc[$i]['parciais'], fn($o) => $this->compativel($o)));
        usort($parciais, fn($a, $b) => $custo($a) <=> $custo($b));
        $opcoes = array_merge($cheias, $parciais, [[[], 0]]);

        $resto = array_values(array_diff($porFazer, [$i]));
        foreach ($opcoes as $opcao) {
            $delta = $this->aplicar($i, $opcao);
            $this->procurar($resto);
            $this->desfazer($i, $opcao, $delta);
            if ($this->parar && $this->melhor !== null) { return; }
        }
    }
}

/* Quantas sessões, no máximo, é possível colocar: cada disciplina dá no
   máximo k (se tiver alguma combinação completa), 1 (se só tiver blocos
   soltos) ou 0 — e nunca mais do que os blocos livres. */
function maximoAlcancavel(array $disc, int $nBlocos): int {
    return min(array_sum(array_column($disc, 'max')), $nBlocos);
}

/* Resolve uma turma com a ocupação dada. Devolve as colocações
   [i => [[slot, salaId], ...]] e o motivo de cada disciplina incompleta. */
function resolverTurma(array $prob, array $ocup, float $prazo): array {
    [$disc, $info] = opcoesDaTurma($prob, $ocup);
    $slots = $prob['slots'];

    $cargaDoc = [];
    foreach ($prob['disciplinas'] as $d) {
        foreach ($d['docentes'] as $t) {
            foreach (DIAS_SEMANA as $di => $dia) { $cargaDoc[$t][$di] = count($ocup['docentes'][$t][$dia] ?? []); }
        }
    }

    $procura = new ProcuraHorarioTurma($disc, $slots, $prob['contaDia'], $prob['usoBloco'], $cargaDoc, $prazo, count($slots));
    $sol = $procura->resolver();

    $colocacoes = []; $motivos = [];
    $pedidas = array_sum(array_column($prob['disciplinas'], 'k'));
    foreach ($prob['disciplinas'] as $i => $d) {
        $escolhidos = $sol['escolha'][$i] ?? [];
        $colocacoes[$i] = array_map(fn($s) => [$s, $info[$i]['possiveis'][$s]], $escolhidos);
        if (count($escolhidos) < $d['k']) {
            $motivos[$i] = motivoIncompleta($prob, $d, $info[$i], count($slots), $pedidas);
        }
    }
    return ['colocacoes' => $colocacoes, 'motivos' => $motivos, 'sessoes' => $sol['sessoes'], 'pen' => $sol['pen']];
}

/* Para cada disciplina: em que blocos pode ficar (docente livre + sala
   livre + RN09 com as sessões já marcadas) e as combinações possíveis. */
function opcoesDaTurma(array $prob, array $ocup): array {
    $slots = $prob['slots'];
    $disc = []; $info = [];
    foreach ($prob['disciplinas'] as $i => $d) {
        $possiveis = []; $docenteLivre = 0; $ocupantes = [];
        foreach ($slots as $s => $sl) {
            $livre = true;
            foreach ($d['docentes'] as $t) {
                if (intervaloOcupado($ocup['docentes'][$t][$sl['dia']] ?? [], $sl['hi'], $sl['hf'])) { $livre = false; $ocupantes[$t] = true; }
            }
            if (!$livre) { continue; }
            $docenteLivre++;
            $sala = false;
            foreach ($d['salas'] as $sid) {
                if ($sid === null || !intervaloOcupado($ocup['salas'][$sid][$sl['dia']] ?? [], $sl['hi'], $sl['hf'])) { $sala = $sid; break; }
            }
            if ($sala === false) { continue; }
            // RN09 com as sessões que já existem (marcadas à mão)
            $okDias = true;
            foreach ($d['diasExistentes'] as $de) { if (abs($de - $sl['di']) <= 1) { $okDias = false; } }
            if (!$okDias) { continue; }
            $possiveis[$s] = $sala;
        }
        $espaco = function (array $dias) use ($d): int {
            $todos = array_merge($dias, $d['diasExistentes']);
            sort($todos);
            $p = 0;
            for ($j = 1; $j < count($todos); $j++) { $p += max(0, $todos[$j] - $todos[$j - 1] - 2); }
            return $p;
        };
        $cheias = []; $parciais = [];
        $ids = array_keys($possiveis);
        if ($d['k'] === 1) {
            foreach ($ids as $s) { $cheias[] = [[$s], $espaco([$slots[$s]['di']])]; }
        } else {
            foreach ($ids as $a => $s1) {
                foreach (array_slice($ids, $a + 1) as $s2) {
                    if (abs($slots[$s1]['di'] - $slots[$s2]['di']) >= 2) {
                        $cheias[] = [[$s1, $s2], $espaco([$slots[$s1]['di'], $slots[$s2]['di']])];
                    }
                }
            }
            foreach ($ids as $s) { $parciais[] = [[$s], 5]; }   // só 1 de 2: penalizado
        }
        $disc[$i] = ['k' => $d['k'], 'cheias' => $cheias, 'parciais' => $parciais, 'docentes' => $d['docentes'],
                     'max' => $cheias ? $d['k'] : ($possiveis ? 1 : 0)];
        $info[$i] = ['possiveis' => $possiveis, 'docenteLivre' => $docenteLivre, 'ocupantes' => array_keys($ocupantes), 'temCheias' => (bool)$cheias];
    }
    return [$disc, $info];
}

/* Porque é que uma disciplina não ficou completa — a frase que o
   coordenador lê no resumo, para saber o que corrigir. */
function motivoIncompleta(array $prob, array $d, array $info, int $nBlocos, int $pedidas): string {
    $nomeDoc = fn(int $t) => $prob['nomesDocentes'][$t] ?? "docente #$t";
    $nomeSala = fn(?int $s) => $s ? ($prob['nomesSalas'][$s] ?? "sala #$s") : '';
    if ($nBlocos === 0) {
        return 'não há blocos livres no turno desta turma';
    }
    if ($info['docenteLivre'] === 0) {
        $quem = array_map($nomeDoc, $info['ocupantes'] ?: $d['docentes']);
        return (count($quem) > 1 ? 'os docentes ' . implode(' e ', $quem) . ' estão ocupados'
                                 : 'o docente ' . $quem[0] . ' está ocupado')
            . ' noutras turmas em todos os blocos livres desta turma';
    }
    if (!$info['possiveis']) {
        if ($d['lab']) { return 'o laboratório "' . $nomeSala($d['lab']) . '" está ocupado em todos os blocos em que o docente está livre'; }
        if ($d['forcada']) { return 'a sala escolhida ("' . $nomeSala($d['forcada']) . '") está ocupada em todos os blocos em que o docente está livre'; }
        if ($d['diasExistentes']) { return 'a sessão que falta teria de ficar num dia sem a outra sessão nem a seguir a ela, e aí o docente ou as salas estão ocupados'; }
        return 'não há nenhuma sala livre nos blocos em que o docente está livre';
    }
    if (!$info['temCheias']) {
        $dias = array_values(array_unique(array_map(fn($s) => $prob['slots'][$s]['dia'], array_keys($info['possiveis']))));
        $dias = array_map(fn($x) => NOMES_DIAS[$x], $dias);
        return 'o docente só está livre (com sala) à ' . implode(' e à ', $dias)
            . ' — as sessões têm de ficar em dias diferentes e não seguidos (RN09)';
    }
    if ($pedidas > $nBlocos) {
        return "não há blocos livres que cheguem no turno ($nBlocos blocos para $pedidas sessões)";
    }
    return 'os blocos em que o docente está livre ficaram ocupados por outras disciplinas desta turma';
}

/* Junta à ocupação as aulas de uma solução (para a turma seguinte). */
function acrescentarOcupacao(array &$ocup, array $prob, array $res): void {
    foreach ($res['colocacoes'] as $i => $lista) {
        $d = $prob['disciplinas'][$i];
        foreach ($lista as [$s, $sala]) {
            $sl = $prob['slots'][$s];
            foreach ($d['docentes'] as $t) { $ocup['docentes'][$t][$sl['dia']][] = [$sl['hi'], $sl['hf']]; }
            if ($sala) { $ocup['salas'][$sala][$sl['dia']][] = [$sl['hi'], $sl['hf']]; }
        }
    }
}

/* ---------------- gravação ---------------- */

function gravarGeracao(PDO $pdo, array $prob, array $res): array {
    $horario = $prob['horario']; $turma = $prob['turma'];
    $ins = $pdo->prepare(
        "INSERT INTO aulas (horario_id, disciplina_id, docente_id, docente_regente_id, docente_assistente_id,
            sala_id, tipo_bloco, dia_semana, hora_inicio, hora_fim)
         VALUES (?,?,?,?,?,?,'Aula',?,?,?)");
    $criadas = 0; $avisos = []; $naoAlocadas = []; $usados = [];

    foreach ($prob['disciplinas'] as $i => $d) {
        $colocadas = 0;
        foreach ($res['colocacoes'][$i] ?? [] as [$s, $sala]) {
            $sl = $prob['slots'][$s];
            $cand = [
                'horario_id' => (int)$horario['id'], 'turma_id' => (int)$turma['id'], 'tipo_bloco' => 'Aula',
                'disciplina_id' => $d['id'], 'docente_id' => $d['regente'], 'docente_regente_id' => $d['regente'],
                'docente_assistente_id' => $d['assistente'], 'sala_id' => $sala, 'subgrupo' => null,
                'dia_semana' => $sl['dia'], 'hora_inicio' => $sl['hi'], 'hora_fim' => $sl['hf'],
            ];
            // Confirmação final pelo motor de conflitos (a procura usa uma
            // cópia em memória da ocupação; isto garante que nada passa).
            $problemas = verificarConflitos($pdo, $cand);
            if (array_filter($problemas, fn($p) => $p['bloqueante'])) {
                error_log('Geração automática: bloco recusado pelo motor de conflitos — '
                    . implode(' | ', array_column($problemas, 'mensagem')));
                continue;
            }
            $ins->execute([$horario['id'], $d['id'], $d['regente'], $d['regente'], $d['assistente'], $sala, $sl['dia'], $sl['hi'], $sl['hf']]);
            foreach (array_filter($problemas, fn($p) => !$p['bloqueante']) as $p) {
                $avisos[] = "{$d['nome']} (" . NOMES_DIAS[$sl['dia']] . " {$sl['hi']}–{$sl['hf']}): {$p['mensagem']}";
            }
            $usados[$s] = true;
            $criadas++; $colocadas++;
        }
        if ($colocadas < $d['k']) {
            $motivo = $res['motivos'][$i] ?? 'um conflito impediu a gravação (ver o log do servidor)';
            $parcial = $d['carga'] > 1 && ($colocadas + $d['carga'] - $d['k']) > 0
                ? ($colocadas + $d['carga'] - $d['k']) . ' de ' . $d['carga'] . ' sessões — ' : '';
            $naoAlocadas[] = $d['nome'] . ' (' . $parcial . $motivo . ')';
        }
    }
    foreach ($prob['semRegente'] as $nome) { $naoAlocadas[] = "$nome (sem docente regente definido)"; }

    // O que sobrar dentro do turno preenche-se com Estudo Autónomo.
    $insEstudo = $pdo->prepare(
        "INSERT INTO aulas (horario_id, tipo_bloco, dia_semana, hora_inicio, hora_fim) VALUES (?, 'Estudo_Autonomo', ?, ?, ?)");
    $estudo = 0;
    foreach ($prob['slots'] as $s => $sl) {
        if (!isset($usados[$s])) { $insEstudo->execute([$horario['id'], $sl['dia'], $sl['hi'], $sl['hf']]); $estudo++; }
    }

    iniciarRevisaoSeRascunho($pdo, $horario);
    registarHistorico($pdo, (int)$horario['id'],
        "Geração automática: $criadas aula(s) criada(s), $estudo bloco(s) de Estudo Autónomo"
        . ($naoAlocadas ? ', ' . count($naoAlocadas) . ' disciplina(s) por completar' : ''));

    return ['criadas' => $criadas, 'estudo' => $estudo, 'nao_alocadas' => $naoAlocadas, 'avisos' => $avisos];
}

/* ---------------- pontos de entrada ---------------- */

/* Gera o horário de UMA turma. Não toca em aulas marcadas à mão nem em
   Atividades; $limparPrimeiro apaga tudo antes (ação explícita no editor).
   $salaForcada: todas as aulas geradas nessa sala (continua sujeita à RN02). */
function gerarHorarioAutomatico(PDO $pdo, array $horario, array $turma, array $curso, bool $limparPrimeiro = false, ?int $salaForcada = null): array {
    $stmt = $pdo->prepare("SELECT id FROM aulas WHERE horario_id = ?" . ($limparPrimeiro ? '' : " AND tipo_bloco = 'Estudo_Autonomo'"));
    $stmt->execute([$horario['id']]);
    removerBlocos($pdo, (int)$horario['id'], array_column($stmt->fetchAll(), 'id'));
    if ($limparPrimeiro) {
        registarHistorico($pdo, (int)$horario['id'], "Horário limpo antes de gerar automaticamente");
    }
    garantirPastoral($pdo, $horario, $turma);

    $salas = $pdo->query("SELECT * FROM salas ORDER BY capacidade, id")->fetchAll();
    $prob = prepararProblema($pdo, $horario, $turma, $curso, $salaForcada, $salas);
    $res = resolverTurma($prob, carregarOcupacao($pdo), microtime(true) + GERADOR_SEGUNDOS_TURMA);
    return gravarGeracao($pdo, $prob, $res);
}

/* Gera de uma vez todas as turmas de um curso que ainda não têm horário
   publicado (as publicadas não são tocadas — altera-se essas turma a
   turma, no editor, com aviso aos docentes). Como as turmas do mesmo ano
   partilham disciplinas e docentes, a ordem importa: experimentam-se
   várias ordens em memória e grava-se a que coloca mais sessões. */
function gerarHorariosDoCurso(PDO $pdo, array $curso, ?int $salaForcada = null): array {
    $inicio = microtime(true);
    $alvo = []; $ignoradas = [];
    foreach (turmasDoCurso($pdo, (int)$curso['id']) as $t) {
        $h = obterHorarioDaTurma($pdo, (int)$t['id']);
        $rotulo = (int)$t['ano_curricular'] . 'º ano · ' . rotuloRegime($t['regime']) . ' · Turma ' . $t['nome_turma'];
        if ($h['estado'] === 'Publicado') {
            $ignoradas[] = ['turma' => $rotulo, 'ignorada' => 'já publicado — não foi alterado (gera esta turma no editor, se for mesmo preciso)'];
            continue;
        }
        apagarEstudoAutonomo($pdo, (int)$h['id']);
        garantirPastoral($pdo, $h, $t);
        $alvo[] = ['turma' => $t, 'horario' => $h, 'rotulo' => $rotulo];
    }

    $salas = $pdo->query("SELECT * FROM salas ORDER BY capacidade, id")->fetchAll();
    $ocupBase = carregarOcupacao($pdo);
    $probs = [];
    foreach ($alvo as $k => $a) { $probs[$k] = prepararProblema($pdo, $a['horario'], $a['turma'], $curso, $salaForcada, $salas); }

    // Ordens a experimentar: mais difícil primeiro (sessões / blocos livres),
    // a inversa, a natural e algumas baralhadas (sempre as mesmas).
    $chaves = array_keys($probs);
    $dif = fn($k) => array_sum(array_column($probs[$k]['disciplinas'], 'k')) / max(1, count($probs[$k]['slots']));
    $porDificuldade = $chaves; usort($porDificuldade, fn($a, $b) => $dif($b) <=> $dif($a));
    $ordens = [$porDificuldade, array_reverse($porDificuldade), $chaves];
    mt_srand(20260913);
    for ($n = 0; $n < 5 && count($chaves) > 2; $n++) { $o = $chaves; shuffle($o); $ordens[] = $o; }
    $ordens = array_values(array_unique(array_map(fn($o) => implode(',', $o), $ordens)));

    // Máximo teórico: cada turma sozinha, sem as outras turmas do curso a
    // ocuparem os docentes. Nenhuma ordem passa disto.
    $maximoCurso = 0;
    foreach ($probs as $p) { $maximoCurso += maximoAlcancavel(opcoesDaTurma($p, $ocupBase)[0], count($p['slots'])); }
    $melhor = null;
    $porOrdem =max(1.0, (GERADOR_SEGUNDOS_CURSO - (microtime(true) - $inicio)) / max(1, count($ordens)));
    foreach ($ordens as $n => $ordemTxt) {
        $ordem = $ordemTxt === '' ? [] : array_map('intval', explode(',', $ordemTxt));
        $ocup = $ocupBase; $sessoes = 0; $pen = 0; $sols = [];
        $porTurma = $porOrdem / max(1, count($ordem));
        foreach ($ordem as $k) {
            $res = resolverTurma($probs[$k], $ocup, microtime(true) + $porTurma);
            acrescentarOcupacao($ocup, $probs[$k], $res);
            $sols[$k] = $res; $sessoes += $res['sessoes']; $pen += $res['pen'];
        }
        if ($melhor === null || $sessoes > $melhor['sessoes'] || ($sessoes === $melhor['sessoes'] && $pen < $melhor['pen'])) {
            $melhor = ['ordem' => $ordem, 'sols' => $sols, 'sessoes' => $sessoes, 'pen' => $pen];
        }
        // Outras ordens só servem para colocar mais sessões: se esta já
        // colocou tudo o que cabe em cada turma, não vale a pena esperar.
        if ($sessoes >= $maximoCurso || microtime(true) - $inicio > GERADOR_SEGUNDOS_CURSO) { break; }
    }

    $resultado = [];
    foreach ($melhor['ordem'] ?? [] as $k) {
        $r = gravarGeracao($pdo, $probs[$k], $melhor['sols'][$k]);
        $resultado[$k] = ['turma' => $alvo[$k]['rotulo']] + $r;
    }
    ksort($resultado);
    return ['turmas' => array_merge(array_values($resultado), $ignoradas)];
}
