<?php

/**
 * FailsafeContainerTest.php
 *
 * Copyright 2020 Danny Damsky
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package coffeephp\di
 * @author Danny Damsky <dannydamsky99@gmail.com>
 * @since 2020-07-16
 */

declare(strict_types=1);

namespace CoffeePhp\Di\Test\Unit;

use CoffeePhp\Di\Contract\ContainerInterface;
use CoffeePhp\Di\Data\Binding;
use CoffeePhp\Di\FailsafeContainer;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyA;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyAInterface;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyB;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyBInterface;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyC;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyCInterface;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyD;
use CoffeePhp\Di\Test\Mock\ComplexDependencies\DependencyDInterface;
use CoffeePhp\QualityTools\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

/**
 * Class FailsafeContainerTest
 * @package coffeephp\di
 * @since 2020-07-16
 * @author Danny Damsky <dannydamsky99@gmail.com>
 * @see FailsafeContainer
 */
final class FailsafeContainerTest extends TestCase
{
    /**
     * @see FailsafeContainer::create()
     */
    public function testCreate(): void
    {
        $container = new FailsafeContainer();

        /** @var DependencyA $dependencyA */
        $dependencyA = $container->create(DependencyA::class);

        assertInstanceOf(DependencyA::class, $dependencyA);
        assertSame('A', $dependencyA->getA());

        $mixedHashMap = ['b' => 'B'];

        /** @var DependencyB $dependencyB */
        $dependencyB = $container->create(DependencyB::class, $mixedHashMap);

        assertInstanceOf(DependencyB::class, $dependencyB);
        assertSame('A', $dependencyB->getA());
        assertSame('B', $dependencyB->getB());

        $mixedHashMap['b'] = $dependencyB;

        /** @var DependencyC $dependencyC */
        $dependencyC = $container->create(DependencyC::class, $mixedHashMap);

        assertInstanceOf(DependencyC::class, $dependencyC);
        assertSame('A', $dependencyC->getA());
        assertSame('B', $dependencyC->getB());
        assertSame('C', $dependencyC->getC());

        $mixedHashMap['c'] = $dependencyC;

        /** @var DependencyD $dependencyD */
        $dependencyD = $container->create(DependencyD::class, $mixedHashMap);

        assertInstanceOf(DependencyD::class, $dependencyD);
        assertSame('A', $dependencyD->getA());
        assertSame('B', $dependencyD->getB());
        assertSame('C', $dependencyD->getC());
        assertSame('D', $dependencyD->getD());

        assertInstanceOf(DependencyA::class, $container->create(DependencyAInterface::class));
    }

    /**
     * @see FailsafeContainer::get()
     */
    public function testGet(): void
    {
        $container = new FailsafeContainer();
        $container->bind(DependencyAInterface::class, DependencyA::class);
        $container->bind(DependencyBInterface::class, DependencyB::class, ['b' => 'B']);
        $container->bind(DependencyCInterface::class, DependencyC::class);
        $container->bind(DependencyDInterface::class, DependencyD::class);
        $container->bind(DependencyB::class, DependencyB::class, ['b' => 'B']);

        assertSame($container->get(DependencyD::class), $container->get(DependencyDInterface::class));
        assertSame($container->get(DependencyC::class), $container->get(DependencyCInterface::class));
        assertSame($container->get(DependencyB::class), $container->get(DependencyBInterface::class));
        assertSame($container->get(DependencyA::class), $container->get(DependencyAInterface::class));

        $container->bind('a', 'b');
        $container->bind('b', 'c');
        $container->bind('c', 'd');
        $container->bind('d', 'e');
        $container->bind('e', DependencyDInterface::class);

        assertSame($container->get(DependencyD::class), $container->get('a'));
        assertSame($container->get(DependencyD::class), $container->get(DependencyD::class));

        assertSame($container, $container->get(PsrContainerInterface::class));
        assertSame($container, $container->get(ContainerInterface::class));
        assertSame($container, $container->get(FailsafeContainer::class));
    }

    /**
     * @see FailsafeContainer::get()
     */
    public function testGetWithCustomConstructor(): void
    {
        $depA = new Binding(DependencyA::class);
        $depB = new Binding(DependencyB::class, ['b' => 'B']);
        $depC = new Binding(DependencyC::class);
        $depD = new Binding(DependencyD::class);
        $container = new FailsafeContainer(
            [
                DependencyA::class => $depA,
                DependencyAInterface::class => $depA,
                DependencyB::class => $depB,
                DependencyBInterface::class => $depB,
                DependencyC::class => $depC,
                DependencyCInterface::class => $depC,
                DependencyD::class => $depD,
                DependencyDInterface::class => $depD,
                'a' => new Binding('b'),
                'b' => new Binding('c'),
                'c' => new Binding('d'),
                'd' => new Binding('e'),
                'e' => $depD,
            ]

        );

        assertSame($container->get(DependencyD::class), $container->get(DependencyDInterface::class));
        assertSame($container->get(DependencyC::class), $container->get(DependencyCInterface::class));
        assertSame($container->get(DependencyB::class), $container->get(DependencyBInterface::class));
        assertSame($container->get(DependencyA::class), $container->get(DependencyAInterface::class));

        assertSame($container->get(DependencyD::class), $container->get('a'));
        assertSame($container->get(DependencyD::class), $container->get(DependencyD::class));

        assertSame($container, $container->get(PsrContainerInterface::class));
        assertSame($container, $container->get(ContainerInterface::class));
        assertSame($container, $container->get(FailsafeContainer::class));
    }

    /**
     * @see FailsafeContainer::has()
     */
    public function testHas(): void
    {
        $container = new FailsafeContainer();

        assertTrue($container->has(FailsafeContainer::class));
        assertTrue($container->has(ContainerInterface::class));

        $container->bind(DependencyAInterface::class, DependencyA::class);
        $container->bind(DependencyBInterface::class, DependencyB::class);
        $container->bind(DependencyCInterface::class, DependencyC::class);
        $container->bind(DependencyDInterface::class, DependencyD::class);
        $container->bind(DependencyB::class, DependencyB::class, ['b' => 'B']);

        assertTrue($container->has(DependencyAInterface::class));
        assertFalse($container->has(DependencyD::class));
        $container->get(DependencyD::class);
        assertTrue($container->has(DependencyD::class));
    }

    /**
     * Extra functionality not covered by original tests.
     */
    public function testUniqueFunctionality(): void
    {
        $container = new FailsafeContainer();

        $container->bind(DependencyAInterface::class, DependencyBInterface::class);
        $container->bind(DependencyBInterface::class, DependencyCInterface::class);
        $container->bind(DependencyCInterface::class, DependencyDInterface::class);
        $container->bind(DependencyDInterface::class, DependencyD::class);
        $container->bind(DependencyB::class, DependencyB::class, ['b' => 'B']);

        assertInstanceOf(DependencyD::class, $container->get(DependencyAInterface::class));

        $container->bind(
            'uniqueDependencyC',
            DependencyC::class,
            [
                'c' => 'uniqueC',
                'b' => DependencyB::class,
            ]
        );

        $container->bind(
            'uniqueDependencyD',
            DependencyD::class,
            [
                'c' => 'uniqueDependencyC',
                'b' => DependencyB::class,
            ]
        );

        assertSame(
            'uniqueC',
            $container->get('uniqueDependencyD')->getC()
        );
    }
}
