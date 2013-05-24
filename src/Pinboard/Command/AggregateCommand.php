<?php
namespace Pinboard\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AggregateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('aggregate')
            ->setDescription('Aggregate data from source tables and save to report tables')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $silexApp = $this->getApplication()->getSilex();
        $db = $silexApp['db'];
        
        $sql = '
            SELECT 
                server_name, hostname, COUNT(*) AS cnt
            FROM 
                request
            GROUP BY 
                server_name, hostname
        ';
        
        $servers = $db->fetchAll($sql);
        
        $subselectTemplate = '
            (
                SELECT 
                    r.%s 
                FROM 
                    request r 
                WHERE 
                    r.server_name = r2.server_name AND r.hostname = r2.hostname 
                ORDER BY 
                    r.%s DESC LIMIT %d, 1
            ) 
            as %s
        ';
        
        $sql = '';
        foreach($servers as $server) {
            $sql .= '
                INSERT INTO ipm_report_2_by_hostname_and_server
                    (server_name, hostname, req_time_90, req_time_95, req_time_99, req_time_100, 
                     mem_peak_usage_90, mem_peak_usage_95, mem_peak_usage_99, mem_peak_usage_100,
                     doc_size_90, doc_size_95, doc_size_99, doc_size_100)
                SELECT 
                    r2.server_name, 
                    r2.hostname, 
                    ' . sprintf($subselectTemplate, 'req_time', 'req_time', $server['cnt'] * (1 - 0.90), 'req_time_90') . ',
                    ' . sprintf($subselectTemplate, 'req_time', 'req_time', $server['cnt'] * (1 - 0.95), 'req_time_95') . ',
                    ' . sprintf($subselectTemplate, 'req_time', 'req_time', $server['cnt'] * (1 - 0.99), 'req_time_99') . ',
                    max(req_time) as req_time_100,
                    ' . sprintf($subselectTemplate, 'mem_peak_usage', 'mem_peak_usage', $server['cnt'] * (1 - 0.90), 'mem_peak_usage_90') . ',
                    ' . sprintf($subselectTemplate, 'mem_peak_usage', 'mem_peak_usage', $server['cnt'] * (1 - 0.95), 'mem_peak_usage_95') . ',
                    ' . sprintf($subselectTemplate, 'mem_peak_usage', 'mem_peak_usage', $server['cnt'] * (1 - 0.99), 'mem_peak_usage_99') . ',
                    max(mem_peak_usage) as mem_peak_usage_100,
                    ' . sprintf($subselectTemplate, 'doc_size', 'doc_size', $server['cnt'] * (1 - 0.90), 'doc_size_90') . ',
                    ' . sprintf($subselectTemplate, 'doc_size', 'doc_size', $server['cnt'] * (1 - 0.95), 'doc_size_95') . ',
                    ' . sprintf($subselectTemplate, 'doc_size', 'doc_size', $server['cnt'] * (1 - 0.99), 'doc_size_99') . ',
                    max(doc_size) as doc_size_100
                FROM 
                    request r2
                WHERE
                    r2.server_name = "' . $server['server_name'] . '" and r2.hostname = "' . $server['hostname'] . '"
            ;';        
        }
        $db->query($sql);
        
        $date = date('Y-m-d H:i:s', strtotime('-1 month'));
        
        $sql = '
            INSERT INTO ipm_info 
                (req_count, time_total, ru_utime_total, ru_stime_total, time_interval, kbytes_total) 
            SELECT * FROM info;

            INSERT INTO ipm_report_by_hostname 
                (
                    req_count, req_per_sec, req_time_total, req_time_percent, req_time_per_sec, 
                    ru_utime_total, ru_utime_percent, ru_utime_per_sec, 
                    ru_stime_total, ru_stime_percent, ru_stime_per_sec, 
                    traffic_total, traffic_percent, traffic_per_sec,
                    hostname
                )
            SELECT * FROM report_by_hostname;            
            
            INSERT INTO ipm_report_by_hostname_and_script 
                (
                    req_count, req_per_sec, req_time_total, req_time_percent, req_time_per_sec, 
                    ru_utime_total, ru_utime_percent, ru_utime_per_sec, 
                    ru_stime_total, ru_stime_percent, ru_stime_per_sec, 
                    traffic_total, traffic_percent, traffic_per_sec,
                    hostname, script_name
                )
            SELECT * FROM report_by_hostname_and_script;
            
            INSERT INTO ipm_report_by_hostname_and_server 
                (
                    req_count, req_per_sec, req_time_total, req_time_percent, req_time_per_sec, 
                    ru_utime_total, ru_utime_percent, ru_utime_per_sec, 
                    ru_stime_total, ru_stime_percent, ru_stime_per_sec, 
                    traffic_total, traffic_percent, traffic_per_sec,
                    hostname, server_name
                )
            SELECT * FROM report_by_hostname_and_server;
            
            INSERT INTO ipm_report_by_hostname_server_and_script 
                (
                    req_count, req_per_sec, req_time_total, req_time_percent, req_time_per_sec, 
                    ru_utime_total, ru_utime_percent, ru_utime_per_sec, 
                    ru_stime_total, ru_stime_percent, ru_stime_per_sec, 
                    traffic_total, traffic_percent, traffic_per_sec,
                    hostname, server_name, script_name
                )
            SELECT * FROM report_by_hostname_server_and_script;
            
            INSERT INTO ipm_report_by_server_and_script 
                (
                    req_count, req_per_sec, req_time_total, req_time_percent, req_time_per_sec, 
                    ru_utime_total, ru_utime_percent, ru_utime_per_sec, 
                    ru_stime_total, ru_stime_percent, ru_stime_per_sec, 
                    traffic_total, traffic_percent, traffic_per_sec,
                    server_name, script_name
                )
            SELECT * FROM report_by_server_and_script;            

            INSERT INTO ipm_report_by_server_name 
                (
                    req_count, req_per_sec, req_time_total, req_time_percent, req_time_per_sec, 
                    ru_utime_total, ru_utime_percent, ru_utime_per_sec, 
                    ru_stime_total, ru_stime_percent, ru_stime_per_sec, 
                    traffic_total, traffic_percent, traffic_per_sec,
                    server_name
                )
            SELECT * FROM report_by_server_name;            

            INSERT INTO 
                ipm_report_status (req_count, status, hostname, server_name, script_name)
            SELECT 
                count(req_count), status, hostname, server_name, script_name
            FROM 
                request
            GROUP BY
                status, hostname, server_name, script_name
            ;
        ';
        $db->query($sql);
        
        $sql = '
            INSERT INTO 
                ipm_status_details (server_name, hostname, script_name, status)
            SELECT 
                server_name, hostname, script_name, status 
            FROM 
                request
            WHERE
                status >= 500
            GROUP BY
                server_name, hostname, script_name
            LIMIT 
                25
        ';
        $db->query($sql);

        $sql = '';
        foreach($servers as $server) {
            $maxReqTime = 0.3;
            if (isset($silexApp['params']['logging']['long_request_time']['global'])) {
                $maxReqTime = $silexApp['params']['logging']['long_request_time']['global'];
            }
            if (isset($silexApp['params']['logging']['long_request_time'][$server['server_name']])) {
                $maxReqTime = $silexApp['params']['logging']['long_request_time'][$server['server_name']];
            }
            $sql .= '
                INSERT INTO ipm_req_time_details
                    (server_name, hostname, script_name, req_time)
                SELECT 
                    server_name, hostname, script_name, max(req_time)
                FROM 
                    request
                WHERE
                    server_name = "' . $server['server_name'] . '" AND hostname = "' . $server['hostname'] . '" AND req_time > ' . (float)$maxReqTime . '
                GROUP BY
                    server_name, hostname, script_name
                ORDER BY
                    req_time DESC
                LIMIT
                    10
            ;';        
        }
        $db->query($sql);

        $sql = '';
        foreach($servers as $server) {
            $maxMemoryUsage = 4000;
            if (isset($silexApp['params']['logging']['heavy_request']['global'])) {
                $maxMemoryUsage = $silexApp['params']['logging']['heavy_request']['global'];
            }
            if (isset($silexApp['params']['logging']['heavy_request'][$server['server_name']])) {
                $maxMemoryUsage = $silexApp['params']['logging']['heavy_request'][$server['server_name']];
            }

            $sql .= '
                INSERT INTO ipm_mem_peak_usage_details
                    (server_name, hostname, script_name, mem_peak_usage)
                SELECT 
                    server_name, hostname, script_name, max(mem_peak_usage)
                FROM 
                    request
                WHERE
                    server_name = "' . $server['server_name'] . '" AND hostname = "' . $server['hostname'] . '" AND mem_peak_usage > ' . (int)$maxMemoryUsage . '
                GROUP BY
                    server_name, hostname, script_name
                ORDER BY
                    mem_peak_usage DESC
                LIMIT
                    10
            ;';        
        }
        $db->query($sql);
        
        $output->writeln('<info>Data are aggregated successfully</info>');
    }
}