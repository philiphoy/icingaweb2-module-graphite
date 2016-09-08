# Graphite module for Icinga Web 2

## General Information

Enable the graphite carbon cache writer: http://docs.icinga.org/icinga2/latest/doc/module/icinga2/chapter/monitoring-basics#graphite-carbon-cache-writer

All perfdata metrics will be automatically included as graphs however if you just want a subset, the host or service then needs to have custom vars of the form vars.graphite_keys =["key1","key2"] where key1 key2 represent perfdata stats you want to see.

You can configure the graphite metric keys formats by using standard-ish icinga2 macros.
## Installation

Just extract this to your Icinga Web 2 module folder in a folder called graphite.

(Configuration -> Modules -> graphite -> enable). Check the modules config tab right there.

NB: It is best practice to install 3rd party modules into a distinct module
folder like /usr/share/icingaweb2/modules. In case you don't know where this
might be please check the module path in your Icinga Web 2 configuration.

### Setting up carbon-cache for icinga2
For graphs to show up correctly in Icinga Web 2, the storage schema for icinga2 needs to be added on the host running Graphite. Usually this configuration file can be found in `/etc/carbon/` or `/opt/graphite/conf/`, depending on how your Graphite server was installed.

Edit the file `storage-schemas.conf` and add the following configuration example for icinga2:

```
[icinga2_internals]
pattern = ^icinga2\..*\.(max_check_attempts|reachable|current_attempt|execution_time|latency|state|state_type)
retentions = 5m:7d

[icinga2_default]
pattern = ^icinga2\.
retentions = 5m:10d,30m:90d,360m:4y
```

**Hint**: Make sure to add these lines before any wild card directives (eg. `pattern = .*`), otherwise they will never match!

Refer to [Configuring Carbon](http://graphite.readthedocs.io/en/latest/config-carbon.html?highlight=storage) for more details.

##Configuration
There are various configuration settings to tweak how the module behaves and ensure that it aligns with how the graphite carbon cache writer is set up:

``base_url``
A fully formed url 
* *http://graphite.com/render?*

``legacy_mode``
To support older versions of the writer pre-icinga2 2.4 where true - is replaced with _ 
* *false*

``service_name_template``
Macro template for the service name 
* *icinga2.$host.name$.services.$service.name$.$service.check_command$.perfdata.$metric$.value*

``host_name_template``
Macro template for the host name 
* *icinga2.$host.name$.host.$host.check_command$.perfdata.$metric$.value*

``graphite_args_template ``
Macro template for the small image where $target$ is replaced with the metric name 
* *&target=$target$&source=0&width=300&height=120&hideAxes=true&lineWidth=2&hideLegend=true&colorList=049BAF*

``graphite_large_args_template ``
Macro template for the large image 
* *&target=$target$&source=0&width=800&height=700&colorList=049BAF&lineMode=connected*

## Customizing
As mentioned in General Information above, you can use vars.graphite_keys to limit [or define] the graphs you want to see.

### Limiting graphs
In our example there are 6 metrics available in perfdata from the check_tomcat script:
* used
* free
* max
* currentThreadCount
* maxThreads
* currentThreadsBusy

To just see the basic graphs of used heap and currentThreadCount, add the following to your Service definition.

    vars.graphite_keys = ["used", "currentThreadCount"]

### Composite Graphs
In some cases, composite graphs are more useful.  Used Heap doesn't make sense unless you know the Max Heap size.  To create some useful composite graphs, add the following to your Service definition.

    vars.graphite_keys = ["{used,max}", "{currentThreadsBusy,currentThreadCount}"]

### Graph Labels
Composite graph definitions can get long and cumbersome, so you can define Labels for each graph.  To make short, user-friendly labels for the composite graphs, add the following to your Service definition.

    vars.graphite_keys = ["{used,max}", "{currentThreadsBusy,currentThreadCount}"]
    vars.graphite_labels = ["Heap", "Threads"]

### Removing graphs
To disable all graphs, add the following to your Service definition.  Note that you can put any string as the value; the presence of this variable is enough.

    vars.grpahite_disable = "x"

## Hats off to

This module borrows a lot from https://github.com/Icinga/icingaweb2-module-pnp4nagios

## What it looks like

![screen shot of graphite graph](https://raw.githubusercontent.com/philiphoy/icingaweb2-module-graphite/master/Capture.PNG)
