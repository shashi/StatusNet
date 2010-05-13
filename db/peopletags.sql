/* this will eventually be merged with statusnet.sql */

/* temporarily keep existing profile_tag data */
alter table profile_tag rename to profile_tag_old;

/* profile_tag now stores tag metadata. also, remote peopletags are stored here (similar to user_group) */
create table profile_tag (
    id integer auto_increment primary key comment 'unique identifier',
    user_id integer not null comment 'user making the tag' references user (id), /* this may be a remote profile :) */
    tag varchar(64) not null comment 'hash tag',
    description text comment 'description for the tag',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    uri varchar(255) unique key comment 'universal identifier',
    mainpage varchar(255) comment 'page for tag info info to link to',

    index profile_tag_tag_idx (tag)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* populate metadata use tag itself as the
   description till the user provides a new one */
/* XXX: uri, modified */

insert into profile_tag (user_id, tag, description)
    select distinct tagger, tag, tag from profile_tag_old;

/* a table to store tagger - tagged - tag mappings */
create table profile_tag_map (
   tagger integer not null comment 'user making the tag' references user (id),  /* this is local */
   tagged integer not null comment 'profile tagged' references profile (id),    /* this can also be remote */
   tag integer not null comment 'hash tag' references profile_tag (id),
   modified timestamp comment 'date the tag was added',

   constraint primary key (tagger, tagged, tag),
   index profile_tag_modified_idx (modified),
   index profile_tag_tagger_tag_idx (tagger, tag),
   index profile_tag_tagged_idx (tagged)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* populate the new profile_tag_map table */
insert into profile_tag_map (tagger, tagged, tag, modified)
    select profile_tag_old.tagger, profile_tag_old.tagged, profile_tag.id, profile_tag_old.modified
        from profile_tag_old join profile_tag
        on (profile_tag.user_id=profile_tag_old.tagger and profile_tag.tag=profile_tag_old.tag);

drop table profile_tag_old;

create table profile_tag_inbox (
    profile_tag_id integer not null comment 'peopletag receiving the message' references profile_tag (id),
                /* note: ^ this cannot be a remote peopletag/self-tag (one with a remote uri) */
    notice_id integer not null comment 'notice received' references notice (id),
    created datetime not null comment 'date the notice was created',

    constraint primary key (profile_tag_id, notice_id),
    index profile_tag_inbox_created_idx (created),
    index profile_tag_inbox_notice_id_idx (notice_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_tag_subscription (
    profile_tag_id integer not null comment 'foreign key to profile_tag' references profile_tag (id),
    profile_id integer not null comment 'foreign key to profile table' references profile (id),

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (profile_tag_id, profile_id),
    index profile_tag_subscription_profile_id_idx (profile_id),
    index profile_tag_subscription_created_idx (created)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* note: add a profile_tag_id column to ostatus_profile table,
   this is plugin stuff... goes in Ostatus_profile::schemadef() */
