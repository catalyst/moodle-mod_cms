# Caching in CMS datasources.

In the CMS plugin, the generated content is cached on two levels.

Each datasource for a CMS module caches its own data. When the module generates the HTML content via it's mustache template, it too is cached. What this allows, is that when a datasource changes it data, the other datasources contributing to a modules content do not have to recalculate their own data.

For each datasource, the cache key is made up of two parts. A config key fragment and an instance key fragment. They are combined to get the full key used for caching the datasource data.

The config key fragment represents the configuration. It should change whenever the configuration changes, and be unique for the configuration, but is otherwise the same for all instances. A hash of the config parameters is usually used for this key and is stored in the cms_types.customdata field

The instance key fragment represents the instance. It should change whenever the instance data changes, and be unique for each instance. The hash/rev is stored cms.customdata.

The data for each datasource for each instance is cached under a key made up of a combination of the config and instance cache key.

The overall content is also cached, using a key derived from the combination of all the datasource keys.

Sometimes a datsource cannot be cached. It's data needs to be recalculated each time. Therefore the overall rendered content for the CMS instance cannot be cached either. In this case the function get_full_cache_key() should return null. This acts as a kind of veto that prevents the CMS from caching the rendered content.

There are several traits the define functions for different caching strategies. nullcache is used when caching is not done. hashcache is used when a hash of the data is used for the instance key. revcache is used for revision based instance cahce keys.
