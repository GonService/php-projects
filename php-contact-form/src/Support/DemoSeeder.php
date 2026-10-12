<?php

declare(strict_types=1);

namespace ContactForm\Support;

use ContactForm\Domain\MessageRepository;
use DateTimeImmutable;
use PDO;

/** Fictional messages for demos and screenshots. */
final class DemoSeeder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(DateTimeImmutable $now): int
    {
        $repo = new MessageRepository($this->pdo);
        $rows = [
            [-2900, 'Rafael Moreira', 'rafael.moreira@hotelrecanto.com.br', '(54) 99654-1020', 'Hotel Recanto da Serra', 'quote',
                "Good morning! We run a 40-room hotel in Gramado and need preventive maintenance for the air conditioning units before the summer.\n\nCould you send a quote for a yearly contract with quarterly visits?", true],
            [-1500, 'Beatriz Fontana', 'beatriz@fontanaarquitetura.com.br', '', 'Fontana Arquitetura', 'partnership',
                'We design commercial interiors and are looking for a technical partner for electrical and network installations in our projects in Porto Alegre.', true],
            [-380, 'João Pedro Albuquerque', 'jp.albuquerque@gmail.com', '(51) 98123-4567', '', 'support',
                "The access control panel installed last month shows an error after power outages and only works again after a manual restart.\n\nIs there a technician available this week?", false],
            [-95, 'Luciana Prates', 'luciana.prates@colegiosaofrancisco.edu.br', '(51) 3332-1188', 'Colégio São Francisco', 'quote',
                "Hello,\n\nWe need a quote for the electrical inspection required by the fire department (PPCI) in our two school buildings, about 3,200 m² in total.\n\nThe inspection must be done during the school holidays, between December 20 and January 31. Please include the technical report (ART) in the proposal.\n\nThank you,\nLuciana Prates\nAdministrative coordinator", false],
            [-20, 'Carlos Eduardo Vieira', 'cadu.vieira@vieiralog.com.br', '(51) 99901-2233', 'Vieira Logística', 'other',
                'Do you also work with solar panel cleaning and inspection? We have 120 panels on the warehouse roof in Canoas.', false],
        ];
        foreach ($rows as [$minutes, $name, $email, $phone, $company, $topic, $body, $read]) {
            $at = $now->modify("{$minutes} minutes");
            $id = $repo->save(
                ['name' => $name, 'email' => $email, 'phone' => $phone, 'company' => $company, 'topic' => $topic, 'body' => $body],
                hash('sha256', 'demo-' . $email),
                'Mozilla/5.0 (demo data)',
                $at->format('Y-m-d H:i:s'),
            );
            if ($read) {
                $repo->markRead($id, $at->modify('+30 minutes')->format('Y-m-d H:i:s'));
            }
        }

        return count($rows);
    }
}
