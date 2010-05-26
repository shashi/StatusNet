/* this will eventually be merged with statusnet.sql */

/* profile_list stores tag metadata. also, remote peopletags are stored here (similar to user_group) */
create table profile_list (
    id integer auto_increment unique key comment 'unique identifier',
    tagger integer not null comment 'user making the tag' references profile (id), /* this may be a remote profile :) */
    tag varchar(64) not null comment 'hash tag',
    description text comment 'description for the tag',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    uri varchar(255) unique key comment 'universal identifier',
    mainpage varchar(255) comment 'page for tag info info to link to',

    constraint primary key (tagger, tag),
    index profile_tag_tag_idx (tag)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* populate metadata use tag itself as the
   description till the user provides a new one */
/* XXX: uri, modified */

insert into profile_list (tagger, tag, description)
    select distinct tagger, tag, tag from profile_tag;

create table profile_tag_inbox (
    profile_tag_id integer not null comment 'peopletag receiving the message' references profile_tag (id),
    notice_id integer not null comment 'notice received' references notice (id),
    created datetime not null comment 'date the notice was created',

    constraint primary key (profile_tag_id, notice_id),
    index profile_tag_inbox_created_idx (created),
    index profile_tag_inbox_notice_id_idx (notice_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_tag_subscription (
    profile_tag_id integer not null comment 'foreign key to profile_tag' references profile_list (id),

    profile_id integer not null comment 'foreign key to profile table' references profile (id),
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (profile_tag_id, profile_id),
    index profile_tag_subscription_profile_id_idx (profile_id),
    index profile_tag_subscription_created_idx (created)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;


