<?php
use Illuminate\Support\Str;
return [
    'name'=>env('HORIZON_NAME'),'domain'=>env('HORIZON_DOMAIN'),'path'=>env('HORIZON_PATH','horizon'),'use'=>'default',
    'prefix'=>env('HORIZON_PREFIX',Str::slug(env('APP_NAME','dataforge'),'_').'_horizon:'),'middleware'=>['web'],
    'waits'=>['redis:imports'=>60,'redis:imports-analyze'=>60,'redis:imports-plan'=>60,'redis:default'=>60],
    'trim'=>['recent'=>60,'pending'=>60,'completed'=>60,'recent_failed'=>10080,'failed'=>10080,'monitored'=>10080],
    'silenced'=>[],'silenced_tags'=>[],'metrics'=>['trim_snapshots'=>['job'=>24,'queue'=>24]],'fast_termination'=>false,'memory_limit'=>128,
    'defaults'=>[
        'imports'=>['connection'=>'redis','queue'=>['imports'],'balance'=>'auto','autoScalingStrategy'=>'time','maxProcesses'=>1,'maxTime'=>0,'maxJobs'=>0,'memory'=>256,'tries'=>1,'timeout'=>360,'nice'=>0],
        'control'=>['connection'=>'redis','queue'=>['imports-analyze','imports-plan'],'balance'=>'auto','autoScalingStrategy'=>'time','maxProcesses'=>1,'maxTime'=>0,'maxJobs'=>0,'memory'=>192,'tries'=>1,'timeout'=>360,'nice'=>0],
        'maintenance'=>['connection'=>'redis','queue'=>['default'],'balance'=>'simple','maxProcesses'=>1,'maxTime'=>0,'maxJobs'=>0,'memory'=>128,'tries'=>1,'timeout'=>180,'nice'=>0],
    ],
    'environments'=>[
        'production'=>['imports'=>['maxProcesses'=>8,'balanceMaxShift'=>2,'balanceCooldown'=>2],'control'=>['maxProcesses'=>2,'balanceMaxShift'=>1,'balanceCooldown'=>3],'maintenance'=>['maxProcesses'=>1]],
        'local'=>['imports'=>['maxProcesses'=>4],'control'=>['maxProcesses'=>2],'maintenance'=>['maxProcesses'=>1]],
    ],
    'watch'=>['app','bootstrap','config/**/*.php','database/**/*.php','public/**/*.php','resources/**/*.php','routes','composer.lock','composer.json','.env'],
];
