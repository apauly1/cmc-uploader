<?php

namespace CmC;

use Symfony\Component\Console\Output\ConsoleOutput;

use CmC\Helper\ComposerHelper;
use CmC\Helper\PrintHelper;
use CmC\Helper\ParserHelper;
use CmC\Helper\UploadHelper;

class CheckMyComposer
{
    public static function check()
    {
        $output = new ConsoleOutput();

        $output->writeLn('Checking dependencies...');
        $output->writeLn('');

        $requirements = ComposerHelper::getInstalledPackages();
        $requirements = ComposerHelper::getLatestPackages($requirements);
        $requirements = ParserHelper::unsetProperty($requirements, 'constraintsOfVersion');
        $requirements = ParserHelper::parseStatutes($requirements, array('status', 'statusInConstraints'));

        PrintHelper::printTable($output, $requirements);
        $output->writeLn('');
    }

    public static function synchronize()
    {
        //$composerContent = file_get_contents('composer.json');

        $output = new ConsoleOutput();

        if (!$syncToken = static::tokenExists()) {
            $output->writeLn(PHP_EOL.'<error>Synchronization with CheckMyComposer failed.</error>');
            $output->writeLn('It seems there is no "cmc-token" parameter in your composer.json');
            $output->writeLn('or "cmc_token" file in your project to synchronize your dependencies.');
            $output->writeLn('<info>Please follow this link for more help: http://everythingisawesome.com</info>'.PHP_EOL);
            exit();
        }

        $output->writeLn('Synchronizing your dependencies with CheckMyComposer server...');

        $requirements = ComposerHelper::getInstalledPackages();
        $requirements = ComposerHelper::getLatestPackages($requirements);

        $responseCurl = UploadHelper::uploadWithCurl($requirements, $syncToken);

        if ($responseCurl) {
            $output->writeLn($responseCurl);
            exit;
        }

        // If curl request failed, we try with php sockets
        $responseSocket = UploadHelper::uploadWithSocket($requirements, $syncToken);

        if ($responseSocket) {
            $output->writeLn($responseSocket);
            exit;
        }

        $output->writeLn(PHP_EOL.'<error>Connection with CheckMyComposer\'s server failed (try with php sockets and curl)</error>');
        $output->writeLn('<info>Please check your connection and follow this link for more help: http://everythingisawesome.com</info>'.PHP_EOL);
    }

    public static function synchronizerResponse($output, $response)
    {
        if ($response) {
            if (stripos($response, '[error]')) {
                $output->writeLn('<error>'.$response.'</error>');
            } else {
                $output->writeLn($response);
            }
        }
    }

    /**
     * Return the value of token file (cmc_token|cmc_token.txt) or token env var (cmc_token) if exists
     * @return string $tokenInFile|$tokenInEnv
     */
    protected static function tokenExists()
    {
        // if (!$tokenInFile = @file_get_contents('cmc_token.txt')) {
        //     $tokenInFile = @file_get_contents('cmc_token');
        // }

        // $tokenInEnv = getenv('cmc_toke');

        // if (!$tokenInEnv && !$tokenInFile) {
        //     return false;
        // }

        // return $tokenInFile ?: $tokenInEnv;

        try {
            $composerContent = ComposerHelper::readFile('composer.json');
            $tokenInComposer = $composerContent['extra']['cmc-token'];
        } catch(\Exception $e) {
            $tokenInComposer = null;
        }

        if (!$tokenInFile = @file_get_contents('cmc-token.txt')) {
            $tokenInFile = @file_get_contents('cmc-token');
        }

        if (!$tokenInFile = @file_get_contents('cmc_token.txt')) {
            $tokenInFile = @file_get_contents('cmc_token');
        }

        return $tokenInComposer ?: $tokenInFile;
    }
}