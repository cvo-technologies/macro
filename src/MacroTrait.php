<?php

namespace Macro;

use Cake\Core\Plugin;
use Cake\Utility\Hash;
use DebugKit\DebugTimer;
use Macro\Error\MacroException;
use Macro\Error\MissingMacroMethodException;
use Macro\Macro\Macro;
use Macro\Macro\MacroRegistry;

trait MacroTrait
{

    /**
     * @var MacroRegistry
     */
    private $_registry = null;

    /**
     * @param $identifier
     * @param array $parameters
     * @param null $context
     * @throws MacroException
     * @return mixed
     */
    public function runMacro($identifier, array $parameters = [], $context = null, array $options = [])
    {
        $options = Hash::merge(
            [
                'validate' => false
            ],
            $options
        );

        if (Plugin::loaded('DebugKit')) {
            DebugTimer::start(__d('macro', 'Macro: {0}', $identifier));
        }

        $macroParts = explode('::', $identifier);
        $name = $macroParts[0];
        $method = isset($macroParts[1]) ? $macroParts[1] : 'run';

        $this->getMacroRegistry()->reset();

        $config = [];

        /** @var Macro $macro */
        try {
            $macro = $this->getMacroRegistry()->load($name, $config);

            if ($context) {
                $macro->context($context);
            }
        }
        catch (MacroException $missing) {
            if (!$options['validate']) {
                throw $missing;
            }

            return $missing;
        }

        $callable = [$macro, $method];
        if (!is_callable($callable)) {
            $exception = new MissingMacroMethodException('Unknown method \'' . $method . '\' in macro ' . get_class($macro));
            if (!$options['validate']) {
                throw $exception;
            }

            return $exception;
        }

        $result = call_user_func_array($callable, $parameters);

        $elapsedTime = null;
        if (Plugin::loaded('DebugKit')) {
            $elapsedTime = DebugTimer::elapsedTime(__d('macro', 'Macro: {0}', $identifier), 10) * 1000;

            DebugTimer::stop(__d('macro', 'Macro: {0}', $identifier));
        }

        DebugMacro::record($identifier, $parameters, $context, $options, $result, $elapsedTime);

        if ($options['validate']) {
            return true;
        }

        return $result;
    }

    /**
     * @param $content
     * @param null $context
     * @throws MissingMacroException
     * @return mixed
     */
    public function executeMacros($content, $context = null, array $options = [])
    {
        $options = Hash::merge(
            [
                'validate' => false,
                'context' => 'default'
            ],
            $options
        );

        $errors = [];

        while ((!isset($count)) || $count > 1) {
            $content = preg_replace_callback(
                '/(?P<macro>\{\=(?P<name>[^\:\=\(\|]+)(?:\:\:(?P<method>[^\(\=\|]+))?(?:\((?P<parameters>.*)\))?(?:\|(?P<context>[^\(\=]+))?\=\})/',
                function (array $matches) use ($content, $context, $options, &$errors) {
                    $identifier = $matches['name'];
                    $parameters = [];

                    $startPosition = strpos($content, $matches['macro']);

                    if (!empty($matches['method'])) {
                        $identifier .= '::' . $matches['method'];
                    }
                    if (!empty($matches['parameters'])) {
                        $parameters = array_map('trim', explode(', ', $matches['parameters']));
                    }
                    if (!empty($matches['context'])) {
                        $options['context'] = $matches['context'];
                    }
                    if ((is_array($context)) && (isset($context[$options['context']]))) {
                        $context = $context[$options['context']];
                    }

                    $parameters = array_map([$this, 'executeMacros'], $parameters, [$context]);

                    $result = $this->runMacro($identifier, $parameters, $context, $options);
                    if ($options['validate']) {
                        if (!$result instanceof \Exception) {
                            return $result;
                        }

                        $errors[] = [
                            'position' => $startPosition,
                            'macro' => $matches['macro'],
                            'exception' => $result,
                            'message' => $result->getMessage()
                        ];
                    }
                    return $result;
                },
                $content, -1, $count
            );
        }

        if ($options['validate']) {
            return (empty($errors)) ? true : $errors;
        }

        return $content;
    }

    /**
     * @return MacroRegistry
     */
    protected function getMacroRegistry() {
        if (!$this->_registry) {
            $this->_registry = new MacroRegistry();
        }

        return $this->_registry;
    }

}
