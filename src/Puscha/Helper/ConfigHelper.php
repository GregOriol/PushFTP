<?php

namespace Puscha\Helper;

use JsonMapper;
use JsonMapper_Exception;
use Swaggest\JsonSchema\Schema;
use Psr\Log\LoggerInterface;
use Puscha\Exception\ConfigValidationException;
use Puscha\Helper\Symfony\Console\IndentedOutputFormatter;
use Puscha\Model\Config;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigHelper
{
    const PRINT_VALUE_NULL  = '(null)';
    const PRINT_VALUE_EMPTY = '(empty)';

    /**
     * @param string          $file
     * @param LoggerInterface $logger
     *
     * @return Config|null
     */
    public static function load($file, LoggerInterface $logger)
    {
        $logger->info('Reading file...');

        if (!file_exists($file)) {
            $logger->error('File doesn\'t exist');

            return null;
        }

        $data = file_get_contents($file);
        if ($data === false) {
            $logger->error('Could not read file');

            return null;
        }

        $fileInfo = new \SplFileInfo($file);
        $fileInfo->getExtension();
        switch ($fileInfo->getExtension()) {
            case 'json':
                $data = self::decodeJson($data, $logger);
                break;
            case 'yaml':
                $data = self::decodeYaml($data, $logger);
                break;
            default:
                $logger->error('Unknown file extension');
        }

        $logger->info('Validating against schema...');

        $schema = Schema::import('./src/schema.json');
        try {
            $schema->in($data);
        } catch (\Swaggest\JsonSchema\Exception $e) {
            $logger->error($e->getMessage());
        }

        $logger->info('Validating against mapping...');

        try {
            // Mapping the json
            $mapper = new JsonMapper();
            $mapper->setLogger($logger);
            $mapper->bExceptionOnMissingData                = true;
            $mapper->bStrictObjectTypeChecking              = true;
            $mapper->bStrictSimpleTypeConversionChecking    = true;
            $mapper->bSimpleTypeLossyDataConversionChecking = true;

            /** @var Config $config */
            $config = $mapper->map($data, new Config());
        } catch (JsonMapper_Exception $e) {
            // Getting the deepest error
            $last   = $e;
            $deeper = $e->getPrevious();
            while ($deeper !== null) {
                $last   = $deeper;
                $deeper = $deeper->getPrevious();
            }

            $logger->error($last->getMessage());

            return null;
        }

        ConfigHelper::resolveLinks($config);

        $logger->info('Validating against rules...');

        try {
            ConfigHelper::validate($config);
        } catch (ConfigValidationException $e) {
            $logger->error($e->getMessage());

            return null;
        }

        return $config;
    }

    /**
     * Additional validations not performed by the mapper
     *
     * @param Config $config
     *
     * @throws ConfigValidationException
     */
    protected static function validate($config)
    {
        // throw new ConfigValidationException('{}->excludes['.$key.']->'.$exclude.' is not a string');
    }

    /**
     * @param Config $config
     *
     * @return int The number of links resolved.
     */
    protected static function resolveLinks($config)
    {
        $globalExcludes    = $config->getExcludes();
        $globalPermissions = $config->getPermissions();

        $count = 0;

        foreach ($config->getProfiles() as $profileName => $profile) {
            // Setting profile name
            $profile->setName($profileName);

            // Resolving excludes
            $excludes = $profile->getExcludes();
            $resolvedExcludes = array();
            foreach ($excludes as $key) {
                if (strpos($key, '@') === 0) {
                    $reference = substr($key, 1);
                    if (isset($globalExcludes[$reference])) {
                        foreach ($globalExcludes[$reference] as $k) {
                            $resolvedExcludes[] = $k;
                        }

                        $count += 1;
                    }
                } else {
                    $resolvedExcludes[] = $key;
                }
            }
            $profile->setExcludes($resolvedExcludes);

            // Resolving permissions
            $permissions = $profile->getPermissions();
            $resolvedPermissions = array();
            foreach ($permissions as $key => $value) {
                if (strpos($key, '@') === 0) {
                    $reference = substr($key, 1);
                    if (isset($globalPermissions[$reference])) {
                        foreach ($globalPermissions[$reference] as $k => $v) {
                            $resolvedPermissions[$k] = $v;
                        }

                        $count += 1;
                    }
                } else {
                    $resolvedPermissions[$key] = $value;
                }
            }
            $profile->setPermissions($resolvedPermissions);
        }

        return $count;
    }

    /**
     * @param Config $config
     *
     * @return string
     */
    public static function print($config)
    {
        if ($config === null) {
            return '';
        }

        $formatter = new IndentedOutputFormatter();
        $output    = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false, $formatter);

        $output->writeln('<comment>Config:</comment>');
        $formatter->incrementLevel();
        $output->writeln(count($config->getProfiles() ?? []).' profiles');
        $output->writeln(count($config->getExcludes() ?? []).' global excludes');
        $output->writeln(count($config->getPermissions() ?? []).' global permissions');
        $output->writeln('');
        foreach ($config->getProfiles() as $profileName => $profile) {
            $output->writeln('Profile <info>'.$profileName.'</info>:');
            $formatter->incrementLevel();

            $target      = $profile->getTarget();
            $tableOutput = new BufferedOutput();
            $table       = new Table($tableOutput);
            $table
                ->setHeaders(array(new TableCell('Target', array('colspan' => 2))))
                ->setRows(array(
                    array('type', $target->getType() ?? self::PRINT_VALUE_NULL),
                    array('host', $target->getHost() ?? self::PRINT_VALUE_NULL),
                    array('port', $target->getPort() ?? self::PRINT_VALUE_NULL),
                    array('mode', DebugHelper::trueFalseNull($target->isPassive(), 'true', 'false', self::PRINT_VALUE_NULL)),
                    array('ssl', DebugHelper::trueFalseNull($target->isSsl(), 'true', 'false', self::PRINT_VALUE_NULL)),
                    array('login', $target->getLogin() ?? self::PRINT_VALUE_NULL),
                    array('key', $target->getKey() ?? self::PRINT_VALUE_NULL),
                    array('password', $target->getPassword() ?? self::PRINT_VALUE_NULL),
                    array('path', $target->getPath() ?? self::PRINT_VALUE_NULL),
                ));
            $table->render();
            $output->writeln($tableOutput->fetch());

            $excludes    = $profile->getExcludes();
            $tableOutput = new BufferedOutput();
            $table       = new Table($tableOutput);
            $table->setHeaders(array(new TableCell('Excludes', array('colspan' => 1))));
            if (!empty($excludes)) {
                foreach ($excludes as $exclude) {
                    $table->addRow(array($exclude ?? self::PRINT_VALUE_NULL));
                }
            } else {
                $table->addRow(array(new TableCell(self::PRINT_VALUE_EMPTY, array('colspan' => 1))));
            }
            $table->render();
            $output->writeln($tableOutput->fetch());

            $permissions = $profile->getPermissions();
            $tableOutput = new BufferedOutput();
            $table       = new Table($tableOutput);
            $table->setHeaders(array(new TableCell('Permissions', array('colspan' => 2))));
            if (!empty($permissions)) {
                foreach ($permissions as $path => $permission) {
                    $table->addRow(array($path, $permission ?? self::PRINT_VALUE_NULL));
                }
            } else {
                $table->addRow(array(new TableCell(self::PRINT_VALUE_EMPTY, array('colspan' => 2))));

            }
            $table->render();
            $output->writeln($tableOutput->fetch());

            $formatter->decrementLevel();
        }

        return $output->fetch();
    }

    /**
     * @param string          $data
     * @param LoggerInterface $logger
     *
     * @return object|null
     */
    protected static function decodeJson($data, LoggerInterface $logger)
    {
        $logger->info('Decoding JSON...');

        $data = json_decode($data);
        if ($data === null) {
            $message = 'Unknown error';

            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    $message = 'No errors';
                    break;
                case JSON_ERROR_DEPTH:
                    $message = 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $message = 'Underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $message = 'Unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    $message = 'Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_UTF8:
                    $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_UTF16:
                    $message = 'Malformed UTF-16 characters, possibly incorrectly encoded';
                    break;
                default:
                    break;
            }

            $logger->error('Could not parse json: '.$message);

            return null;
        }

        return $data;
    }

    /**
     * @param string          $data
     * @param LoggerInterface $logger
     *
     * @return object|null
     */
    protected static function decodeYaml($data, LoggerInterface $logger)
    {
        $logger->info('Decoding YAML...');

        try {
            $data = Yaml::parse($data, Yaml::PARSE_OBJECT_FOR_MAP);
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            $logger->error('Could not parse yaml: '.$e->getMessage());

            return null;
        }

        return $data;
    }
}
