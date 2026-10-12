<?php

declare(strict_types=1);

namespace MiniCrm\Support;

use DateTimeImmutable;
use MiniCrm\Domain\CustomerRepository;
use MiniCrm\Domain\Document;
use MiniCrm\Domain\InteractionRepository;
use MiniCrm\Domain\InteractionType;
use MiniCrm\Domain\ReminderRepository;
use PDO;

/** Fictional demo data. Documents get real check digits so they pass validation. */
final class DemoSeeder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(DateTimeImmutable $today): int
    {
        $customers = new CustomerRepository($this->pdo);
        $interactions = new InteractionRepository($this->pdo);
        $reminders = new ReminderRepository($this->pdo);
        $userId = $this->pdo->query('SELECT MIN(id) FROM users')->fetchColumn();
        $userId = $userId === null || $userId === false ? null : (int) $userId;

        $day = static fn (int $offset, string $time = '10:00'): string => $today->modify("{$offset} days")->format('Y-m-d') . " {$time}:00";
        $date = static fn (int $offset): string => $today->modify("{$offset} days")->format('Y-m-d');

        $rows = [
            ['Padaria Pão Dourado Ltda', 'cnpj', '112223330001', 'Márcia Teixeira', 'marcia@paodourado.com.br', '(51) 3412-8890', 'Canoas', 'active',
                [[-40, 'meeting', 'Kick-off: migrating the order system from spreadsheets.'], [-6, 'call', 'Asked for a quote to add delivery tracking.']],
                [[-3, 'Send delivery tracking proposal']]],
            ['Construtora Vale do Sinos S.A.', 'cnpj', '458712900001', 'Roberto Kuhn', 'roberto.kuhn@valedosinos.eng.br', '(51) 3592-1100', 'Novo Hamburgo', 'active',
                [[-20, 'email', 'Sent the monthly infrastructure report.'], [-1, 'meeting', 'Reviewed backup strategy for the job-site servers.']],
                [[5, 'Quarterly review meeting']]],
            ['Clínica Odontológica Sorriso', 'cnpj', '273645180001', 'Dra. Juliana Prates', 'contato@clinicasorriso.com.br', '(51) 99812-4477', 'Porto Alegre', 'lead',
                [[-2, 'whatsapp', 'Interested in online appointment booking. Prefers contact after 6 pm.']],
                [[0, 'Call back about online booking']]],
            ['Transportes Gaúcho Express', 'cnpj', '090807060001', 'Sérgio Lima', 'sergio@gauchoexpress.com.br', '(54) 3221-6060', 'Caxias do Sul', 'active',
                [[-15, 'call', 'Fleet tracking dashboard is slow on Mondays; scheduled investigation.']],
                [[-8, 'Confirm database index changes in production']]],
            ['Mendes & Filhos Contabilidade', 'cnpj', '334455660001', 'Carla Mendes', 'carla@mendescontabil.com.br', '(51) 3065-2020', 'São Leopoldo', 'lead',
                [[-9, 'email', 'Requested a demo of the invoice import feature.']],
                [[2, 'Send demo link and pricing']]],
            ['Agropecuária Campos Verdes', 'cnpj', '556677880001', 'Lucas Becker', 'lucas@camposverdes.agr.br', '(55) 3312-4545', 'Santa Maria', 'inactive',
                [[-120, 'note', 'Contract paused until the next harvest season.']],
                []],
            ['Ana Paula Ribeiro', 'cpf', '529982247', 'Ana Paula Ribeiro', 'anapaula.ribeiro@gmail.com', '(51) 98455-3210', 'Gravataí', 'lead',
                [[-4, 'call', 'Freelance designer, wants a simple website with a contact form.']],
                [[-1, 'Send website proposal']]],
            ['Ótica Visão Clara', 'cnpj', '778899000001', 'Fernanda Souza', 'fernanda@visaoclara.com.br', '(51) 3477-9012', 'Cachoeirinha', 'active',
                [[-30, 'meeting', 'Delivered the new stock control module.']],
                [[12, 'Check stock module usage after one month']]],
        ];

        $inserted = 0;
        foreach ($rows as [$name, $kind, $base, $contact, $email, $phone, $city, $status, $notes, $followUps]) {
            $document = $base . ($kind === 'cpf' ? Document::cpfCheckDigits($base) : Document::cnpjCheckDigits($base));
            if ($customers->documentTaken($document)) {
                continue;
            }
            $id = $customers->create([
                'name' => $name, 'document' => $document, 'contact_name' => $contact, 'email' => $email,
                'phone' => $phone, 'city' => $city, 'status' => $status,
            ]);
            foreach ($notes as [$offset, $type, $body]) {
                $interactions->add($id, $userId, InteractionType::from($type), $body, $day($offset, sprintf('%02d:%02d', 9 + $id % 8, ($id * 7) % 60)));
            }
            foreach ($followUps as [$offset, $title]) {
                $reminders->add($id, $title, $date($offset));
            }
            ++$inserted;
        }

        return $inserted;
    }
}
