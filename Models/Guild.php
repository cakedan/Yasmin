<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Models;

/**
 * Represents a guild.
 * @todo Implementation
 */
class Guild extends ClientBase {
    protected $channels;
    protected $emojis;
    protected $members;
    protected $presences;
    protected $roles;
    protected $voiceStates;
    
    protected $id;
    protected $available;
    
    protected $name;
    protected $icon;
    protected $splash;
    protected $ownerID;
    protected $large;
    protected $memberCount = 0;
    
    protected $defaultMessageNotifications;
    protected $explicitContentFilter;
    protected $region;
    protected $verificationLevel;
    protected $systemChannelID;
    
    protected $afkChannelID;
    protected $afkTimeout;
    protected $features;
    protected $mfaLevel;
    protected $applicationID;
    
    protected $embedEnabled;
    protected $embedChannelID;
    protected $widgetEnabled;
    protected $widgetChannelID;
    
    protected $createdTimestamp;
    
    /**
     * @internal
     */
    function __construct(\CharlotteDunois\Yasmin\Client $client, array $guild) {
        parent::__construct($client);
        
        $this->client->guilds->set($guild['id'], $this);
        
        $this->channels = new \CharlotteDunois\Yasmin\Models\ChannelStorage($client);
        $this->emojis = new \CharlotteDunois\Yasmin\Models\EmojiStorage($client, $this);
        $this->members = new \CharlotteDunois\Yasmin\Models\GuildMemberStorage($client, $this);
        $this->presences = new \CharlotteDunois\Yasmin\Models\PresenceStorage($client);
        $this->roles = new \CharlotteDunois\Yasmin\Models\RoleStorage($client, $this);
        
        $this->id = $guild['id'];
        $this->available = (empty($guild['unavailable']));
        $this->createdTimestamp = (int) \CharlotteDunois\Yasmin\Utils\Snowflake::deconstruct($this->id)->timestamp;
        
        if($this->available) {
            $this->_patch($guild);
        }
    }
    
    /**
     * @property-read string                                            $id                  The guild ID.
     * @property-read string                                            $name                The guild name.
     * @property-read int                                               $createdTimestamp    The timestmap when this guild was created.
     * @property-read string|null                                       $icon                The guild icon hash, or null.
     * @property-read string|null                                       $splash              The guild splash hash, or null.
     *
     * @property-read \CharlotteDunois\Yasmin\Models\VoiceChannel|null  $afkChannel          The guild afk channel, or null.
     * @property-read \DateTime                                         $createdAt           The DateTime object of createdTimestamp.
     * @property-read \CharlotteDunois\Yasmin\Models\Role               $defaultRole         The guild's default role.
     * @property-read string|null                                       $iconURL             The guild icon URL, or null.
     * @property-read \CharlotteDunois\Yasmin\Models\GuildMember        $me                  The guild member of the client user.
     * @property-read string|null                                       $splashURL           The guild splash URL, or null.
     *
     * @throws \Exception
     */
    function __get($name) {
        if(\property_exists($this, $name)) {
            return $this->$name;
        }
        
        switch($name) {
            case 'afkChannel':
                return $this->channels->get($this->afkChannelID);
            break;
            case 'createdAt':
                return \CharlotteDunois\Yasmin\Utils\DataHelpers::makeDateTime($this->createdTimestamp);
            break;
            case 'defaultRole':
                return $this->roles->get($this->id);
            break;
            case 'iconURL':
                if($this->icon) {
                    return \CharlotteDunois\Yasmin\Constants::format(\CharlotteDunois\Yasmin\Constants::CDN['icons'], $this->id, $this->icon);
                }
                
                return null;
            break;
            case 'me':
                return $this->members->get($this->client->user->id);
            break;
            case 'splashURL':
                if($this->splash) {
                    return \CharlotteDunois\Yasmin\Constants::format(\CharlotteDunois\Yasmin\Constants::CDN['splashes'], $this->id, $this->splash);
                }
                
                return null;
            break;
        }
        
        return parent::__get($name);
    }
    
    /**
     * Bans the given user.
     * @param \CharlotteDunois\Yasmin\Models\GuildMember|\CharlotteDunois\Yasmin\Models\User|string  $user     A guild member or user object, or the user ID.
     * @param int                                                                                    $days     Number of days of messages to delete (0-7).
     * @param string                                                                                 $reason
     * @return \React\Promise\Promise<this>
     */
    function ban($user, int $days = 0, string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($user, $days, $reason) {
            if($user instanceof \CharlotteDunois\Yasmin\Models\User || $user instanceof \CharlotteDunois\Yasmin\Models\GuildMember) {
                $user = $user->id;
            }
            
            $this->client->apimanager()->endpoints->guild->createGuildBan($this->id, $user, $days, $reason)->then(function () use ($resolve) {
                $resolve($this);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Creates a new channel in the guild. Options are as following (all fields except name are optional):
     *
     *  array(
     *      'name' => string,
     *      'type' => 'text'|'voice', (defaults to 'text')
     *      'bitrate' => int, (only for voice channels)
     *      'userLimit' => int, (only for voice channels, 0 = unlimited)
     *      'permissionOverwrites' => array<array|\CharlotteDunois\Yasmin\Models\PermissionOverwrite>
     *      'parentID' => string,
     *      'nsfw' => bool
     *  )
     *
     * @param array   $options
     * @param string  $reason
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface>
     * @throws \InvalidArgumentException
     */
    function createChannel(array $options, string $reason = '') {
        if(empty($options['name'])) {
            throw new \InvalidArgumentException('Channel name can not be empty');
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($options, $reason) {
            $options['type'] = \CharlotteDunois\Yasmin\Constants::CHANNEL_TYPES[($options['type'] ?? 'text')] ?? 0;
            
            if(!empty($options['userLimit'])) {
                $options['user_limit'] = $options['userLimit'];
            }
            unset($options['userLimit']);
            
            if(!empty($options['permissionOverwrites'])) {
                $options['permission_overwrites'] = $options['permissionOverwrites'];
            }
            unset($options['permissionOverwrites']);
            
            $this->client->apimanager()->endpoints->guild->createGuildChannel($this->id, $options, $reason)->then(function ($data) use ($resolve) {
                $channel = \CharlotteDunois\Yasmin\Models\GuildChannel::factory($this->client, $this, $data);
                $resolve($channel);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Creates a new custom emoji in the guild.
     * @param string                                                                                                                                   $file   Filepath or URL, or file data.
     * @param string                                                                                                                                   $name
     * @param array<string|\CharlotteDunois\Yasmin\Models\Role>|\CharlotteDunois\Yasmin\Utils\Collection<string|\CharlotteDunois\Yasmin\Models\Role>   $roles  An role object or the role ID.
     * @param string  $reason
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Models\Emoji>
     */
    function createEmoji(string $file, string $name, $roles = array(), string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($file, $name, $roles, $reason) {
            \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveFileResolvable($file)->then(function ($file) use ($name, $roles, $reason, $resolve, $reject) {
                if($roles instanceof \CharlotteDunois\Yasmin\Utils\Collection) {
                    $roles = $roles->all();
                }
                
                $roles = \array_map($roles, function ($role) {
                    if($role instanceof \CharlotteDunois\Yasmin\Models\Role) {
                        return $role->id;
                    }
                    
                    return $role;
                });
                
                $options = array(
                    'name' => $name,
                    'image' => \CharlotteDunois\Yasmin\Utils\DataHelpers::makeBase64URI($file),
                    'roles' => $roles
                );
                
                $this->client->apimanager()->endpoints->emoji->createGuildEmoji($this->id, $options, $reason)->then(function ($data) use ($resolve) {
                    $emoji = $this->emojis->factory($data);
                    $resolve($emoji);
                }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Creates a new role in the guild. Options are as following (all are optional):
     *
     *  array(
     *      'name' => string,
     *      'permissions' => int|\CharlotteDunois\Yasmin\Models\Permissions,
     *      'color' => int|string,
     *      'hoist' => bool,
     *      'mentionable' => bool
     *  )
     *
     * @param array   $options
     * @param string  $reason
     * @throws \InvalidArgumentException
     */
    function createRole(array $options, string $reason = '') {
        if(!empty($options['color'])) {
            $options['color'] = \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveColor($options['color']);
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($options, $reason) {
            $this->client->apimanager()->endpoints->guild->createGuildRole($this->id, $options, $reason)->then(function ($data) use ($resolve) {
                $role = $this->roles->factory($data);
                $resolve($role);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Deletes the guild.
     * @return \React\Promise\Promise<void>
     */
    function delete() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($options, $reason) {
            $this->client->apimanager()->endpoints->guild->deleteGuild($this->id)->then(function () use ($resolve) {
                $resolve();
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Edits the guild. Options are as following (at least one is required):
     *
     *  array(
     *      'name' => string,
     *      'region' => string,
     *      'verificationLevel' => int,
     *      'explicitContentFilter' => int,
     *      'defaultMessageNotifications' => int,
     *      'afkChannel' => string|\CharlotteDunois\Yasmin\Models\VoiceChannel|null,
     *      'afkTimeout' => int,
     *      'systemChannel' => string|\CharlotteDunois\Yasmin\Models\TextChannel|null,
     *      'owner' => string|\CharlotteDunois\Yasmin\Models\GuildMember,
     *      'icon' => string, (file path or URL, or data)
     *      'splash' => string, (file path or URL, or data)
     *      'region' => string|\CharlotteDunois\Yasmin\Models\VoiceRegion
     *  )
     *
     * @param array   $options
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function edit(array $options, string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($options, $reason) {
            $data = array();
            
            if(!empty($options['name'])) {
                $data['name'] = $options['name'];
            }
            
            if(!empty($options['region'])) {
                $data['region'] = $options['region'];
            }
            
            if(isset($options['verificationLevel'])) {
                $data['verification_level'] = (int) $options['verificationLevel'];
            }
            
            if(isset($options['verificationLevel'])) {
                $data['explicit_content_filter'] = (int) $options['explicitContentFilter'];
            }
            
            if(isset($options['defaultMessageNotifications'])) {
                $data['default_message_notifications'] = (int) $options['defaultMessageNotifications'];
            }
            
            if(\array_key_exists('afkChannel', $options)) {
                $data['afk_channel_id'] = ($options['afkChannel'] === null ? null : ($options['afkChannel'] instanceof \CharlotteDunois\Yasmin\Models\VoiceChannel ? $options['afkChannel']->id : $options['afkChannel']));
            }
            
            if(isset($options['afkTimeout'])) {
                $data['afk_timeout'] = (int) $options['afkTimeout'];
            }
            
            if(\array_key_exists('systemChannel', $options)) {
                $data['system_channel_id'] = ($options['systemChannel'] === null ? null : ($options['systemChannel'] instanceof \CharlotteDunois\Yasmin\Models\TextChannel ? $options['systemChannel']->id : $options['systemChannel']));
            }
            
            if(isset($options['owner'])) {
                $data['owner_id'] = ($options['owner'] instanceof \CharlotteDunois\Yasmin\Models\GuildMember ? $options['owner']->id : $options['owner']);
            }
            
            if(isset($options['region'])) {
                $data['region'] = ($options['region'] instanceof \CharlotteDunois\Yasmin\Models\VoiceRegion ? $options['region']->id : $options['region']);
            }
            
            $files = null;
            $icon = null;
            $splash = null;
            
            if(isset($options['icon'])) {
                $files = \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveFileResolvable($options['icon']);
                $icon = true;
            } elseif(isset($options['splash'])) {
                $files = \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveFileResolvable($options['splash']);
                $splash = true;
            }
            
            if(!$files) {
                $files = \React\Promise\resolve(null);
            }
            
            $files->then(function ($file) use ($icon, $splash, $options) {
                if($file === null) {
                    return null;
                }
                
                if($icon === true) {
                    $icon = $file;
                    $splash = true;
                    return \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveFileResolvable($options['splash']);
                }
                
                return $file;
            })->then(function ($file) use ($icon, $splash, $options) {
                if($file === null) {
                    return null;
                }
                
                if(\is_string($icon) && $splash === true) {
                    $splash = $file;
                } elseif($icon === true) {
                    $icon = $file;
                }
            })->then(function () use ($data, $icon, $splash, $options, $reason, $resolve, $reject) {
                if(\is_string($icon)) {
                    $data['icon'] = $icon;
                }
                
                if(\is_string($splash)) {
                    $data['splash'] = $splash;
                }
                
                $this->client->apimanager()->endpoints->guild->modifyGuild($this->id, $data, $reason)->then(function () use ($resolve) {
                    $resolve($this);
                }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
            });
        }));
    }
    
    /**
     * Fetch audit logs for the guild. Options are as following (all are optional):
     *
     *  array(
     *      'before' => string|\CharlotteDunois\Yasmin\Models\GuildAuditLogEntry,
     *      'after' => string|\CharlotteDunois\Yasmin\Models\GuildAuditLogEntry,
     *      'limit' => int,
     *      'user' => string|\CharlotteDunois\Yasmin\Models\User,
     *      'type' => string|int
     *  )
     *
     * @param array  $options
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Models\GuildAuditLog>
     */
    function fetchAuditLogs(array $options = array()) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($options) {
            if(!empty($options['before'])) {
                $options['before'] = ($options['before'] instanceof \CharlotteDunois\Yasmin\Models\GuildAuditLogEntry ? $options['before']->id : $options['before']);
            }
            
            if(!empty($options['after'])) {
                $options['after'] = ($options['after'] instanceof \CharlotteDunois\Yasmin\Models\GuildAuditLogEntry ? $options['after']->id : $options['after']);
            }
            
            if(!empty($options['user'])) {
                $options['user'] = ($options['user'] instanceof \CharlotteDunois\Yasmin\Models\User ? $options['user']->id : $options['user']);
            }
            
            $this->client->apimanager()->endpoints->guild->getGuildAuditLog($this->id, $options)->then(function ($data) use ($resolve) {
                $audit = new \CharlotteDunois\Yasmin\Models\GuildAuditLog($this->client, $this, $data);
                $resolve($audit);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Fetch all bans of the guild. Resolves with a Collection of array('reason' => string|null, 'user' => User), mapped by the user ID.
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Utils\Collection<array>>
     */
    function fetchBans() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $this->client->apimanager()->endpoints->guild->getGuildBans($this->id)->then(function ($data) use ($resolve) {
                $collect = new \CharlotteDunois\Yasmin\Utils\Collection();
                
                foreach($data as $ban) {
                    $user = $this->client->users->patch($ban['user']);
                    $collect->set($user->id, array(
                        'reason' => ($ban['reason'] ?? null),
                        'user' => $user
                    ));
                }
                
                $resolve($collect);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Fetches all invites of the guild. Resolves with a Collection of Invite instances, mapped by their code.
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Models\Collection<\CharlotteDunois\Yasmin\Models\Invite>>
     */
    function fetchInvites() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $this->client->apimanager()->endpoints->guild->getGuildInvites($this->id)->then(function ($data) use ($resolve) {
                $collect = new \CharlotteDunois\Yasmin\Utils\Collection();
                
                foreach($data as $inv) {
                    $invite = new \CharlotteDunois\Yasmin\Models\Invite($this->client, $inv);
                    $collect->set($invite->code, $invite);
                }
                
                $resolve($collect);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Fetches a specific guild member.
     * @param string  $userid  The ID of the guild member.
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Models\GuildMember>
     */
    function fetchMember(string $userid) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($userid) {
            $this->client->apimanager()->endpoints->guild->getGuildMember($this->id, $userid)->then(function ($data) use ($resolve) {
                $resolve($this->_addMember($data));
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Fetches all guild members.
     * @param string  $query  Limit fetch to members with similar usernames
     * @param int     $limit  Maximum number of members to request
     * @return \React\Promise\Promise<this>
     */
    function fetchMembers(string $query = '', int $limit = 0) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($query, $limit) {
            if($this->members->count() === $this->memberCount) {
                $resolve($this);
                return;
            }
            
            $listener = function ($guild) use(&$listener, $resolve, $reject) {
                if($guild->id !== $this->id) {
                    return;
                }
                
                if($this->members->count() === $this->memberCount) {
                    $this->client->removeListener('guildMembersChunk', $listener);
                    $resolve($this);
                }
            };
            
            $this->client->on('guildMembersChunk', $listener);
            
            $this->client->wsmanager()->send(array(
                'op' => \CharlotteDunois\Yasmin\Constants::OPCODES['REQUEST_GUILD_MEMBERS'],
                'd' => array(
                    'guild_id' => $this->id,
                    'query' => $query ?? '',
                    'limit' => $limit ?? 0
                )
            ))->done(null, array($this->client, 'handlePromiseRejection'));
            
            $this->client->addTimer(120, function () use (&$listener, $reject) {
                if($this->members->count() < $this->memberCount) {
                    $this->client->removeListener('guildMembersChunk', $listener);
                    $reject(new \Exception('Members did not arrive in time'));
                }
            });
        }));
    }
    
    /**
     * Fetches the guild voice regions. Resolves with a Collection of Voice Region instances, mapped by their ID.
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Utils\Collection<\CharlotteDunois\Yasmin\Models\VoiceRegion>>
     */
    function fetchVoiceRegions() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $this->client->apimanager()->endpoints->guild->getGuildVoiceRegions($this->id)->then(function ($data) use ($resolve) {
                $collect = new \CharlotteDunois\Yasmin\Utils\Collection();
                
                foreach($data as $region) {
                    $voice = new \CharlotteDunois\Yasmin\Models\VoiceRegion($this->client, $region);
                    $collect->set($voice->id, $voice);
                }
                
                $resolve($collect);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Leaves the guild.
     * @return \React\Promise\Promise<void>
     */
    function leave() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $this->client->apimanager()->endpoints->user->leaveUserGuild($this->id)->then(function () use ($resolve) {
                $resolve();
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Prunes members from the guild based on how long they have been inactive.
     * @param int     $days
     * @param bool    $dry
     * @param string  $reason
     * @return \React\Promise\Promise<int>
     */
    function pruneMembers(int $days, bool $dry = false, string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($days, $dry, $reason) {
            $method = ($dry ? 'beginGuildPrune' : 'getGuildPruneCount');
            $this->client->apimanager()->endpoints->guild->$method($this->id, $days, $reason)->then(function ($data) use ($resolve) {
                $resolve($data['pruned']);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Edits the AFK channel of the guild.
     * @param string|\CharlotteDunois\Yasmin\Models\VoiceChannel|null  $channel
     * @param string                                                   $reason
     * @return \React\Promise\Promise<this>
     */
    function setAFKChannel($channel, string $reason = '') {
        return $this->edit(array('afkChannel' => $channel), $reason);
    }
    
    /**
     * Edits the AFK timeout of the guild.
     * @param int     $channel
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function setAFKTimeout(int $timeout, string $reason = '') {
        return $this->edit(array('afkTimeout' => $timeout), $reason);
    }
    
    /**
     * Batch-updates the guild's channels positions. Channels is an array of channelID (string)|GuildChannelInterface => position (int) pairs.
     * @param array   $channels
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function setChannelPositions(array $channels, string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($channels, $reason) {
            $options = array();
            
            foreach($channels as $chan => $position) {
                if($chan instanceof \CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface) {
                    $chan = $chan->id;
                }
                
                $options[] = array('id' => $chan, 'position' => (int) $position);
            }
            
            $this->client->apimanager()->endpoints->guild->modifyGuildChannelPositions($this->id, $options, $reason)->then(function ($data) use ($resolve) {
                $resolve($this);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Edits the level of the explicit content filter.
     * @param int     $filter
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function setExplicitContentFilter(int $filter, string $reason = '') {
        return $this->edit(array('explicitContentFilter' => $filter), $reason);
    }
    
    /**
     * Updates the guild icon.
     * @param string  $icon    A filepath or URL, or data.
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function setIcon(string $icon, string $reason = '') {
        return $this->edit(array('icon' => $icon), $reason);
    }
    
    /**
     * Edits the name of the guild.
     * @param string  $name
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function setName(string $name, string $reason = '') {
        return $this->edit(array('name' => $name), $reason);
    }
    
    /**
     * Sets a new owner for the guild.
     * @param string|\CharlotteDunois\Yasmin\Models\GuildMember  $owner
     * @param string                                             $reason
     * @return \React\Promise\Promise<this>
     */
    function setOwner($owner, string $reason = '') {
        return $this->edit(array('owner' => $owner), $reason);
    }
    
    /**
     * Edits the region of the guild.
     * @param string|\CharlotteDunois\Yasmin\Models\VoiceRegion  $region
     * @param string                                             $reason
     * @return \React\Promise\Promise<this>
     */
    function setRegion($region, string $reason = '') {
        return $this->edit(array('region' => $region), $reason);
    }
    
    /**
     * Updates the guild splash.
     * @param string  $splash  A filepath or URL, or data.
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function setSplash(string $splash, string $reason = '') {
        return $this->edit(array('splash' => $splash), $reason);
    }
    
    /**
     * Edits the system channel of the guild.
     * @param string|\CharlotteDunois\Yasmin\Models\TextChannel|null  $channel
     * @param string                                                  $reason
     * @return \React\Promise\Promise<this>
     */
    function setSystemChannel($channel, string $reason = '') {
        return $this->edit(array('systemChannel' => $channel), $reason);
    }
    
    /**
     * Edits the verification level of the guild.
     * @param int     $level
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     */
    function setVerificationLevel(int $level, string $reason = '') {
        return $this->edit(array('verificationLevel' => $level), $reason);
    }
    
    /**
     * Unbans the given user.
     * @param \CharlotteDunois\Yasmin\Models\User|string  $user     An user object or the user ID.
     * @param string                                      $reason
     * @return \React\Promise\Promise<this>
     */
    function unban($user, string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($user, $reason) {
            if($user instanceof \CharlotteDunois\Yasmin\Models\User) {
                $user = $user->id;
            }
            
            $this->client->apimanager()->endpoints->guild->removeGuildBan($this->id, $user, $reason)->then(function () use ($resolve) {
                $resolve($this);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * @internal
     */
    function _addMember(array $member, bool $initial = false) {
        $guildmember = $this->members->factory($member);
        
        if(!$initial) {
            $this->memberCount++;
        }
        
        return $guildmember;
    }
    
    /**
     * @internal
     */
    function _removeMember(string $userid) {
        if($this->members->has($userid)) {
            $member = $this->members->get($userid);
            $this->members->delete($userid);
            
            $this->memberCount--;
            return $member;
        }
        
        return null;
    }
    
    /**
     * @internal
     */
    function _patch(array $guild) {
        $this->available = (empty($guild['unavailable']));
        
        $this->name = $guild['name'];
        $this->icon = $guild['icon'];
        $this->splash = $guild['splash'];
        $this->ownerID = $guild['owner_id'];
        $this->large =  $guild['large'] ?? $this->large;
        $this->memberCount = $guild['member_count']  ?? $this->memberCount;
        
        $this->defaultMessageNotifications = $guild['default_message_notifications'];
        $this->explicitContentFilter = $guild['explicit_content_filter'];
        $this->region = $guild['region'];
        $this->verificationLevel = $guild['verification_level'];
        $this->systemChannelID = $guild['system_channel_id'];
        
        $this->afkChannelID = $guild['afk_channel_id'];
        $this->afkTimeout = $guild['afk_timeout'];
        $this->features = $guild['features'];
        $this->mfaLevel = $guild['mfa_level'];
        $this->applicationID = $guild['application_id'];
        
        $this->embedEnabled = $guild['embed_enabled'] ?? $this->embedEnabled;
        $this->embedChannelID = $guild['embed_channel_id'] ?? $this->embedChannelID;
        $this->widgetEnabled = $guild['widget_enabled'] ?? $this->widgetEnabled;
        $this->widgetChannelID = $guild['widget_channel_id'] ?? $this->widgetChannelID;
        
        foreach($guild['roles'] as $role) {
            $this->roles->set($role['id'], (new \CharlotteDunois\Yasmin\Models\Role($this->client, $this, $role)));
        }
        
        foreach($guild['emojis'] as $emoji) {
            $this->emojis->set($emoji['id'], (new \CharlotteDunois\Yasmin\Models\Emoji($this->client, $this, $emoji)));
        }
        
        if(!empty($guild['channels'])) {
            foreach($guild['channels'] as $channel) {
                $this->channels->set($channel['id'], \CharlotteDunois\Yasmin\Models\GuildChannel::factory($this->client, $this, $channel));
            }
        }
        
        if(!empty($guild['members'])) {
            foreach($guild['members'] as $member) {
                $this->_addMember($member, true);
            }
        }
        
        if(!empty($guild['presences'])) {
            foreach($guild['presences'] as $presence) {
                $this->presences->factory($presence);
            }
        }
        
        if(!empty($guild['voice_states'])) {
            foreach($guild['voice_states'] as $state) {
                $member = $this->members->get($state['user_id']);
                if($member) {
                    $member->_setVoiceState($state);
                }
            }
        }
    }
}
