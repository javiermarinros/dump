<?php

/**
 * Debugging tool which displays information about any PHP variable, class or exception.
 * Inspired in Krumo by mrasnika (http://krumo.sourceforge.net/)
 * @author Javier Marín (https://github.com/javiermarinros)
 */
abstract class Dump
{

    private static string $_staticURL = '/dump-static';
    private static array $_specialPaths = [];
    public static int $nestingLevel = 5;

    public static function config(string $staticURL = '/dump-static', array $specialPaths = [], int $nestingLevel = 5)
    {
        self::$_staticURL = $staticURL;
        self::$_specialPaths = $specialPaths;
        self::$nestingLevel = $nestingLevel;
    }

    /**
     * @return DumpRender
     */
    private static function _prepareRender()
    {
        $render = new DumpRender();
        $render->format = 'html';
        $render->nestingLevel = self::$nestingLevel;
        $render->assetsURL = self::$_staticURL;

        return $render;
    }

    private static function _loadHelpers()
    {
        require_once dirname(__FILE__) . '/DumpRender.php';
    }

    /**
     * Display information about one or more PHP variables
     *
     * @param mixed $var
     */
    public static function show()
    {
        self::_loadHelpers();

        $render = self::_prepareRender();
        $render->showCaller = false;

        $data = func_get_args();
        echo $render->render($data);
    }

    /**
     * Gets information about one or more PHP variables and return it in HTML code
     *
     * @return string
     */
    public static function render()
    {
        self::_loadHelpers();

        $render = self::_prepareRender();
        $render->showCaller = false;

        $data = func_get_args();
        return $render->render($data);
    }

    /**
     * Gets information about one or more PHP variables and return it in plain text
     */
    public static function printR($data, bool $showTypes = true, ?int $nestingLevel = null, string $format = 'json'): string
    {
        self::_loadHelpers();

        $render = self::_prepareRender();
        $render->format = $format;
        $render->showCaller = false;
        $render->countElements = false;
        $render->showTypes = $showTypes;
        $render->nestingLevel = isset($nestingLevel) ? $nestingLevel : self::$nestingLevel;

        return $render->render([$data]);
    }

    /**
     * Gets information about one or more PHP variables and return it in HTML code.
     *
     * @param mixed $name Name of the analyzed var, or dictionary with several vars and names
     * @param mixed $value
     */
    public static function renderData($name, $value, bool $showCaller = true, bool $showExtraInfo = true): string
    {
        self::_loadHelpers();

        $render = self::_prepareRender();
        $render->showCaller = $showCaller;
        $render->showExtraInfo = $showExtraInfo;
        return $render->render($name, $value);
    }


    /**
     * Analyzes the backtrace generated by debug_backtrace function(),
     * adding contextual information.
     * The result is returned in an array with the keys:
     * 'function': function name
     * 'args': arguments name and value
     * 'file': file where the call occurs
     * 'line': line of the file where the call occurs
     * 'source': source code where the call comes (in HTML format)
     *
     * @param array $ call stack trace to be analyzed, if not use this parameter indicates the call stack before the function
     *
     * @return array
     */
    public static function backtrace(array $trace = null)
    {
        if ($trace === null) {
            $trace = debug_backtrace();
        }

        //"Special" functions
        $special_functions = ['include', 'include_once', 'require', 'require_once'];

        $output = [];
        foreach ($trace as $i => $step) {
            //Get data from the current step
            foreach (['class', 'type', 'function', 'file', 'line', 'args', 'object'] as $param) {
                $$param = isset($step[$param]) ? $step[$param] : null;
            }

            //Source code of the call to this step
            if (!empty($file) && !empty($line)) {
                self::_loadHelpers();
                $source = DumpRender::getSource($step['file'], $step['line']);
            } else {
                $source = '';
            }

            //Arguments
            $function_call = $class . $type . $function;
            $function_args = [];
            if (isset($args)) {
                if (in_array($function, $special_functions)) {
                    $function_args = [self::cleanPath($args[0])];
                } else {
                    if (!function_exists($function) || strpos($function, '{closure}') !== false) {
                        $params = null;
                    } else {
                        if (class_exists('ReflectionMethod', false)) {
                            if (isset($class)) {
                                $reflection = new ReflectionMethod(
                                    $class, method_exists(
                                    $class,
                                    $function
                                ) ? $function : '__call'
                                );
                            } else {
                                $reflection = new ReflectionFunction($function);
                            }
                            $params = $reflection->getParameters();
                        }
                    }

                    foreach ($args as $i => $arg) {
                        if (isset($params[$i])) {
                            // Assign the argument by the parameter name
                            $function_args[$params[$i]->name] = $arg;
                        } else {
                            // Assign the argument by number
                            $function_args[$i] = $arg;
                        }
                    }
                }
            }
            $info = [
                'function' => $function_call,
                'args'     => $function_args,
                'file'     => isset($file) ? self::cleanPath($file) : null,
                'line'     => $line,
                'source'   => $source,
            ];

            if (isset($object)) {
                $info = [
                            'object' => $object
                        ] + $info;
            }

            $output[] = $info;
        }
        return $output;
    }

    /**
     * Renders an abbreviated version of the backtrace
     *
     * @param array $ call stack trace to be analyzed, if not use this parameter indicates the call stack before the function
     *
     * @return string
     */
    public static function backtraceSmall(array $trace = null, bool $html = false, bool $rtl = false): string
    {
        if (!$trace) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace);//Eliminar llamada a backtrace_small
        }

        $output = [];
        foreach ($trace as $step) {
            //Get data from the current step
            foreach (['class', 'type', 'function', 'file', 'line', 'args'] as $param) {
                $$param = isset($step[$param]) ? $step[$param] : '';
            }

            //Generate HTML
            self::_loadHelpers();

            if ($html) {
                $output[] = DumpRender::htmlElement('abbr', ['title' => "{$file}:{$line}"], "{$class}{$type}{$function}");
            } else {
                $output[] = "{$class}{$type}{$function}:{$line}";
            }
        }

        if ($rtl) {
            return implode(' → ', array_reverse($output));
        } else {
            return implode(' ← ', $output);
        }
    }

    /**
     * Renders source code of an specified programming language
     *
     * @param string $code
     * @param string $language
     *
     * @return string
     */
    public static function source($code, $language = 'php', $editable = false, $attrs = [], $theme = 'default')
    {
        self::_loadHelpers();

        $code = htmlspecialchars($code, ENT_NOQUOTES);
        $extra = '';
        $tag = 'pre';
        if ($editable) {
            $extra = 'data-editable="true"';
            if (is_string($editable)) {
                $tag = "textarea name=\"$editable\"";
            } else {
                $tag = 'textarea';
            }
        }
        $extra .= DumpRender::htmlAttributes($attrs);
        return "<{$tag} class=\"dump-code\" data-language=\"{$language}\" data-theme=\"{$theme}\" {$extra}>{$code}</{$tag}>" .
               DumpRender::assetsLoader('init_dump($(".dump-code"),{static_url:"' . self::$_staticURL . '"})', self::$_staticURL);
    }

    /**
     * Clean a path, replacing the special folders defined in the config. E.g.:
     *         /home/project/www/index.php -> APP_PATH/index.php
     *
     * @param bool $restore True for restore a cleared path to its original state
     */
    public static function cleanPath(?string $path, bool $restore = false): string
    {
        if ($path) {
            foreach (self::$_specialPaths as $clean_path => $source_path) {
                if ($restore) {
                    if (strpos($path, $clean_path) === 0) {
                        $path = $source_path . substr($path, strlen($clean_path));
                        break;
                    }
                } else {
                    if (strpos($path, $source_path) === 0) {
                        $path = $clean_path . substr($path, strlen($source_path));
                        break;
                    }
                }
            }
        }

        return $path;
    }
}

//Define shortcuts
if (!function_exists('dump')) {
    /**
     * Echo information about the selected variable.
     * This function can be overwrited for autoload the DUMP class, e.g.:
     * @code
     * function dump() {
     *      if (!class_exists('Dump', FALSE)) {
     *          require SYS_PATH . '/vendor/Dump.php';
     *          Dump::config(...);
     *      }
     *      call_user_func_array(array('Dump', 'show'), func_get_args());
     * }
     * @endcode
     */
    function dump()
    {
        call_user_func_array(['Dump', 'show'], func_get_args());
    }
}

if (!function_exists('dumpdie')) {
    function dumpdie()
    {
        //Clean all output buffers
        while (ob_get_clean()) {
            ;
        }

        //Dump info
        call_user_func_array('dump', func_get_args());

        //Exit
        die(1);
    }
}