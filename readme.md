## The Dude PHP DB toolbox

This simple php class can read from a Mikrotik dude.db sqlite database and extract various data.

Original idea from this forum thread: https://forum.mikrotik.com/viewtopic.php?t=145928

Many thanks to NathanA for the original work and idea.

Based on the awesome work by 0ki: https://github.com/0ki/mikrotik-tools

Usage:

Clone this repo or use composer:
    
`composer require "diegotibi/dude"`

Download the dude.db from your device and run the code like this:

    $db = new \DT\Dude('path_to/dude.db'));
    $devices = $db->fetchDevices();

Take a look in example/index.php for a more detailed example.

### To do:

- Associate various record keys to the correct label
- Separate map links from device ones
- Implement Iterable traits to reduce memory consumption  
- Write an encode method (I really don't have plans on this one)

If you want to improve this class just clone the repo and propose a pull request, I'll be glad to accept any help.