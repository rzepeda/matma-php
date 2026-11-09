<?php

declare(strict_types=1);

namespace Matma\Php\Tests\Business;

use PHPUnit\Framework\TestCase;

final class AgentSkillRulesTest extends TestCase
{
    private const PLATFORMS = ['claude', 'chatgpt', 'antigravity'];

    public function test_all_three_skills_bundle_the_same_authoritative_seven_rules(): void
    {
        $root = dirname(__DIR__, 2);
        $authoritative = file_get_contents($root.'/AIDocumentation/MATMA_Seven_Architectural_Rules.md');
        self::assertIsString($authoritative);
        for ($number = 1; $number <= 7; $number++) {
            self::assertStringContainsString('### Rule '.$number.' —', $authoritative);
        }

        foreach (self::PLATFORMS as $platform) {
            $skillRoot = $root.'/agent-skills/'.$platform;
            $copy = file_get_contents($skillRoot.'/references/MATMA_Seven_Architectural_Rules.md');
            self::assertSame($authoritative, $copy, 'The skill must have the unchanged normative source: '.$platform);

            $main = file_get_contents($skillRoot.'/SKILL.md');
            self::assertIsString($main);
            self::assertStringContainsString('references/MATMA_Seven_Architectural_Rules.md', $main);
            self::assertStringContainsString('references/php-enforcement.md', $main);
            self::assertStringContainsString('references/test-tiers.md', $main);
            self::assertStringContainsString('Mandatory governing rules', $main);
        }
    }

    public function test_all_skill_enforcement_guides_require_correct_real_and_mock_boundaries(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (self::PLATFORMS as $platform) {
            $path = $root.'/agent-skills/'.$platform.'/references/';
            $guide = file_get_contents($path.'php-enforcement.md');
            $tiers = file_get_contents($path.'test-tiers.md');
            self::assertIsString($guide);
            self::assertIsString($tiers);
            self::assertStringContainsString('Only the target class A', $guide);
            self::assertStringContainsString('Exactly the selected pair A and B', $guide);
            self::assertStringContainsString('Participating internal domain/business classes', $guide);
            self::assertStringContainsString('representative real datasets', $guide);
            self::assertStringContainsString('before merge/deployment', $guide);
            foreach (['Unit', 'Interaction', 'Business', 'Regression'] as $tier) {
                self::assertStringContainsString('## '.$tier.' —', $tiers);
            }
        }
    }
}
