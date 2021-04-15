## The Dude database reader

Original idea from this forum thread: https://forum.mikrotik.com/viewtopic.php?t=145928

Many thanks to NathanA for the original work and idea.

Based on the awesome work by 0ki: https://github.com/0ki/mikrotik-tools

Usage:

Clone this repo or use composer:
    
`composer require "ceres/dude"`

Download the dude.db from your device and run the code like this:

    $db = new \ceres\Dude(Yii::getAlias('@static/dude.db'));
    $devices = $db->fetchDevices();

    var_dump($devices);