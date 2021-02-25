<?php

/**
 * ContainerBindingsInstanceMutationTrait.php
 *
 * Copyright 2021 Danny Damsky
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
 * @since 2021-02-25
 */

declare(strict_types=1);

namespace CoffeePhp\Di\Composition;

use CoffeePhp\Di\Data\Binding;

/**
 * Trait ContainerBindingsInstanceMutationTrait
 * @package coffeephp\di
 * @since 2021-02-25
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
trait ContainerBindingsInstanceMutationTrait
{
    use ContainerBindingsInstanceIterationTrait;

    /**
     * @param Binding $binding
     * @param object $instance
     * @noinspection SuspiciousLoopInspection
     */
    private function setInstanceToAllBindings(Binding $binding, object $instance): void
    {
        $binding->setInstance($instance);
        foreach ($this->getBindingInstancesIterator($binding) as $binding) {
            $binding->setInstance($instance);
        }
    }

    /**
     * Get the first binding that has an instantiated
     * object inside it.
     *
     * If no instances found, the last binding found will
     * be returned.
     *
     * @param Binding $binding
     * @return Binding
     * @noinspection SuspiciousLoopInspection
     */
    private function getFirstBindingWithInstance(Binding $binding): Binding
    {
        foreach ($this->getBindingInstancesIterator($binding) as $binding) {
            if ($binding->hasInstance()) {
                break;
            }
        }
        return $binding;
    }

}
