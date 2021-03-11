<?php declare(strict_types=1);

namespace ASOrderInterface\Core\Api\Utilities;

use ASMailService\Core\MailServiceHelper;
use Exception;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;

/*

Establishes a secure file transfer protocoll connection to the server defined in the plugins configuration in shopware administration frontend

*/
class SFTPController
{
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    //server data
    /** @var string $ipAddress */
    private $host;
    /** @var string $port */
    private $port;
    //login data
    /** @var string $username */
    private $username;
    /** @var string $password */
    private $password;
    /** @var string $homeDirectory */
    private $homeDirectory;
    /** @var string $workDir */
    private $workDir;
    /** @var resource */
    private $connection;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->host = $this->systemConfigService->get('ASOrderInterface.config.ipAddress');
        $this->port = $this->systemConfigService->get('ASOrderInterface.config.port');
        $this->username = $this->systemConfigService->get('ASOrderInterface.config.ftpUserName');
        $this->password = $this->systemConfigService->get('ASOrderInterface.config.ftpPassword');
        $this->homeDirectory = $this->systemConfigService->get('ASOrderInterface.config.homeDirectory');
        $this->workDir = $this->systemConfigService->get('ASOrderInterface.config.workingDirectory');
    }

    public function init()
    {
        
    }

    /* Opens the connection to the $host via $port */
    private function openConnection(): bool
    {
        $this->connection = ssh2_connect($this->host, intval($this->port));
        if (! $this->connection) {
            return false;
        }
        return true;
    }

    /* Authentificates on the opened connection */
    private function authConnection():bool
    {
        $authentificationSuccessful = ssh2_auth_password($this->connection, $this->username, $this->password);
        return $authentificationSuccessful;
    }

    /* Writes local file on remote sFTP server */
    public function pushFile(string $originPath, string $destinationPath)
    {
        if ($this->openConnection()) {//connected
            if ($this->authConnection()) {//authenticated
                $sftp = ssh2_sftp($this->connection);
                $stream = fopen('ssh2.sftp://' . intval($sftp) . $this->homeDirectory . $destinationPath, 'w');
                $file = file_get_contents($originPath);
                fwrite($stream, $file);
                fclose($stream);
            }
        }
    }

    /* Copies a file from the remote sFTP server to local disc for evaluation */
    public function pullFile(string $localDir, string $remoteDir)
    {
        if (!function_exists("ssh2_connect")) {
            throw new Exception('Function ssh2_connect not found, you cannot use ssh2 here.');
        }
        
        if(!$connection = ssh2_connect($this->host, intval($this->port)))
        {
            throw new Exception('Unable to connect.');
        }

        if (!ssh2_auth_password($connection, $this->username, $this->password)) 
        {
            throw new Exception('Unable to authenticate.');
        }

        if (!$stream = ssh2_sftp($connection)) 
        {
            throw new Exception('Unable to create a stream.');
        }

        if (!$dir = opendir('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir)) {
            throw new Exception('Could not open the directory.');
        }
        
        $files = array();
        while (false !== ($file = readdir($dir))) {
            if ($file == "." || $file == "..") {
                continue;
            }
            $files[] = $file;
        }
        
        foreach ($files as $file) {
            // echo "Copying file: $file\n";
            
            if (!$remote = @fopen('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir . '/' . $file, 'r')) {
                throw new Exception("Unable to open remote file: $file");
                continue;
            }

            if (!$local = @fopen($localDir . '/' . $file, 'w')) {
                throw new Exception("Unable to create local file: $file\n");
                continue;
            }

            $read = 0;
            $filesize = filesize('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir . '/' . $file);
            while ($read < $filesize && ($buffer = fread($remote, $filesize - $read))) {
                $read += strlen($buffer);
                if (fwrite($local, $buffer) === false) {
                    throw new Exception("Unable to write to local file: $file");
                    break;
                }
            }
            fclose($local);
            ssh2_sftp_unlink($stream, $this->homeDirectory . $remoteDir . '/' . $file);
            fclose($remote);            
        }
        closedir($dir);
    }
}