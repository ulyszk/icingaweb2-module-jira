<?php

namespace Icinga\Module\Jira\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Module\Jira\IcingaCommandPipe;
use Icinga\Module\Jira\Cli\Command;
use Icinga\Module\Jira\IssueTemplate;
use Icinga\Module\Jira\LegacyCommandPipe;

class SendCommand extends Command
{
    /**
     * Create an issue for the given Host or Service problem
     *
     * Use this as a NotificationCommand for Icinga
     *
     * USAGE
     *
     * icingacli jira send problem [options]
     *
     * REQUIRED OPTIONS
     *
     *   --project <project-name>     JIRA project name, like "ITSM"
     *   --issuetype <type-name>      JIRA issue type, like "Incident"
     *   --summary <summary>          JIRA issue summary
     *   --description <description>  JIRA issue description text
     *   --state <state-name>         Icinga state
     *   --host <host-name>           Icinga Host name
     *
     * OPTIONAL
     *
     *   --service <service-name>   Icinga Service name
     *   --template <template-name> Template name (templates.ini section)
     *   --ack-author <author>      Username shown for acknowledgements,
     *                              defaults to "JIRA"
     *   --command-pipe <path>      Legacy command pipe, allows to run without
     *                              depending on a configured monitoring module
     *
     * FLAGS
     *   --verbose    More log information
     *   --trace      Get a full stack trace in case an error occurs
     *   --benchmark  Show timing and memory usage details
     */
    public function problemAction()
    {
        $p = $this->params;

        $host        = $p->shiftRequired('host');
        $service     = $p->shift('service');
        $tplName     = $p->shift('template');
        $ackAuthor   = $p->shift('ack-author', 'JIRA');
        $ackPipe     = $p->shift('command-pipe');

        $jira = $this->jira();
        $key = $jira->eventuallyGetLatestOpenIssueKeyFor($host, $service);

        if ($key === null) {
            $params = [
                'project'     => $p->shiftRequired('project'),
                'issuetype'   => $p->shiftRequired('issuetype'),
                'summary'     => $p->shiftRequired('summary'),
                'description' => $p->shiftRequired('description'),
                'state'       => $p->shiftRequired('state'),
                'host'        => $host,
                'service'     => $service,
            ] + $p->getParams();

            $template = new IssueTemplate();
            if ($tplName) {
                $template->addByTemplateName($tplName);
            }

            $key = $jira->createIssue($template->getFilled($params));

            $ackMessage = "JIRA issue $key has been created";
        } else {
            $ackMessage = "Existing JIRA issue $key has been found";
        }

        try {
            if ($ackPipe) {
                $cmd = new LegacyCommandPipe($ackPipe);
            } else {
                $cmd = new IcingaCommandPipe();
            }
            if ($cmd->acknowledge($ackAuthor, $ackMessage, $host, $service)) {
                Logger::info("Problem has been acknowledged for $key");
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
    }
}
