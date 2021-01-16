<?php

/**
 * FailsafeContainer.php
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
 * @since 2021-16-01
 */

declare(strict_types=1);

namespace CoffeePhp\Di;

use ReflectionClass;
use ReflectionException;
use Throwable;

use function class_exists;
use function get_declared_classes;
use function similar_text;
use function str_starts_with;

/**
 * Class FailsafeContainer
 * @package coffeephp\di
 * @since 2021-16-01
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
final class FailsafeContainer extends Container
{
    private bool $composerClassesAutoloaded = false;

    /**
     * @inheritDoc
     */
    protected function onNonInstantiableImplementationFound(ReflectionClass $class): ReflectionClass
    {
        return $this->getReflectionClassFromAbstraction($class) ?? parent::onNonInstantiableImplementationFound($class);
    }


    /**
     * @param ReflectionClass<object> $abstraction
     * @return ReflectionClass<object>|null
     * @psalm-suppress RedundantCast
     */
    private function getReflectionClassFromAbstraction(ReflectionClass $abstraction): ?ReflectionClass
    {
        $implementation = $abstraction->getName();
        $interfaceName = $abstraction->getShortName();
        $currentSimilarity = -1;
        $class = null;
        foreach (get_declared_classes() as $className) {
            try {
                $classCandidate = new ReflectionClass($className);
                if ($classCandidate->isSubclassOf($implementation) && $classCandidate->isInstantiable()) {
                    $newSimilarity = (int)similar_text($interfaceName, $classCandidate->getShortName());
                    if ($class === null || $newSimilarity > $currentSimilarity) {
                        $currentSimilarity = $newSimilarity;
                        $class = $classCandidate;
                    }
                }
            } catch (ReflectionException) {
                // Do nothing.
            }
        }
        if (!$this->composerClassesAutoloaded && $class === null) {
            $this->requireAllComposerClasses();
            $this->composerClassesAutoloaded = true;
            return $this->getReflectionClassFromAbstraction($abstraction);
        }
        return $class;
    }

    /**
     * @psalm-suppress MixedMethodCall
     */
    private function requireAllComposerClasses(): void
    {
        try {
            foreach (get_declared_classes() as $className) {
                if (str_starts_with($className, 'ComposerAutoloaderInit')) {
                    /**
                     * @var string $namespace
                     * @var string $path
                     */
                    foreach ($className::getLoader()->getClassMap() as $namespace => $path) {
                        class_exists($namespace, true);
                    }
                    break;
                }
            }
        } catch (Throwable) {
            // Do nothing.
        }
    }
}
