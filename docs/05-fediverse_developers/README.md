# Fediverse Developers

This page is mainly for outlining the activities and circumstances Mbin sends out activities and 
how activities, objects and actors are represented.
To communicate between instances, Mbin utilizes the ActivityPub protocol 
([ActivityPub standard](https://www.w3.org/TR/activitypub/), [ActivityPub vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/))
and the [FEP Group federation](https://codeberg.org/fediverse/fep/src/branch/main/feps/fep-1b12.md).

## Context

The `@context` property for all Mbin payloads **should** be this:

```json
%@context%
```

The `/contexts` endpoint resolves to this:

```json
%@context_additional%
```

## Actors

The actors Mbin uses are

- the Instance actor (AP `Application`)
- the User actor (AP `Person`)
- the Magazine actor (AP `Group`)

### Instance Actor

Each instance has an instance actor at `https://instance.tld/i/actor` and `https://instance.tld` (they are the same):

```json
%actor_instance%
```

### User actor

Each registered user has an AP actor at `https://instance.tld/u/username`:

```json
%actor_user%
```

### Magazine actor

Each magazine has an AP actor at `https://instance.tld/m/name`:

```json
%actor_magazine%
```

## Objects

### Threads

```json
%object_entry%
```

### Comments on threads

```json
%object_entry_comment%
```

### Microblogs

```json
%object_post%
```

### Comments on microblogs

```json
%object_post_comment%
```

### Private message

```json
%object_message%
```

### Polls

Polls can be part of all of the above, except private messages. If that is the case, their `type` becomes `Question`.
The deciding factor if an object is a thread or microblog in that case is the existence of the `title` property.

Mbin's representation of polls are mostly the same as Mastodons (see [Mastodon's documentation](https://docs.joinmastodon.org/spec/activitypub/#Question)):

- Poll options are part of the `oneOf` (for single choice polls) or the `anyOf` property (for multiple choice polls)
- Each option has a `name` property, which is both the value and the label of this option
- Each option has a `replies` property containing the amount of votes in their `totalItems` property

```json
%object_poll%
```

#### Poll Votes

A vote in a poll is represented as a `Note` object with a `name` property containing the exact value of the choice that is voted for.
For multiple-choice-polls, multiple activities will be sent, each containing one vote for one choice.

```json
%object_poll_vote%
```

## Collections

### User Outbox

```json
%collection_user_outbox%
```

First Page:

```json
%collection_items_user_outbox%
```

### User Followers

```json
%collection_user_followers%
```

### User Followings

```json
%collection_user_followings%
```

### Magazine Outbox

The magazine outbox endpoint does technically exist, but it just returns an empty JSON object at the moment.
```json
%collection_magazine_outbox%
```

### Magazine Moderators

The moderators collection contains all moderators and is not paginated:

```json
%collection_magazine_moderators%
```

### Magazine Featured

The featured collection contains all threads and is not paginated:

```json
%collection_magazine_featured%
```

### Magazine Followers

The followers collection does not contain items, it only shows the number of subscribed users:

```json
%collection_magazine_followers%
```

## User Activities

### Follow and unfollow

If the user wants to follow another user or magazine:

```json
%activity_user_follow%
```

If the user stops following another user or magazine:

```json
%activity_user_undo_follow%
```

### Accept and Reject

Mbin automatically sends an `Accept` activity when a user receives a `Follow` activity.

```json
%activity_user_accept%
```

### Create

```json
%activity_user_create%
```

### Report

```json
%activity_user_flag%
```

### Vote

When a user votes it is translated to a `Like` activity for an up-vote and a `Dislike` activity for a down-vote. 
Down-votes are not federated, yet.

```json
%activity_user_like%
```

If the vote is removed:

```json
%activity_user_undo_like%
```

### Boost

If a user boosts content:

```json
%activity_user_announce%
```

### Edit account

```json
%activity_user_update_user%
```

### Edit content

```json
%activity_user_update_content%
```

### Delete content

```json
%activity_user_delete%
```

### Delete own account

```json
%activity_user_delete_account%
```

### Lock own content

Only top level content (meaning no comments) can be locked.
When content is locked, comments can no longer be created for it.

```json
%activity_user_lock%
```

## Moderator Activities

### Add or Remove moderator

When a moderator is added:

```json
%activity_mod_add_mod%
```

When a moderator is removed:

```json
%activity_mod_remove_mod%
```

### Pin or Unpin a thread

When a thread is pinned:

```json
%activity_mod_add_pin%
```

When a thread is unpinned:

```json
%activity_mod_remove_pin%
```

### Delete content

```json
%activity_mod_delete%
```

### Ban user from magazine

```json
%activity_mod_ban%
```

### Lock content

When content is locked, comments can no longer be created for it.

```json
%activity_mod_lock%
```

## Admin Activities

### Ban user from instance

```json
%activity_admin_ban%
```

### Delete account

If an admin deletes another user's account the activity actually does not reflect that, it looks exactly as if the user deleted their own account.

```json
%activity_admin_delete_account%
```

## Magazine Activities

### Announce activities

The magazine is mainly there to announce the activities users do with it as the audience.
The announced type can be `Create`, `Update`, `Add`, `Remove`, `Announce`, `Delete`, `Like`, `Dislike`, `Flag` and `Lock`.
`Announce(Flag)` activities are only sent to instances with moderators of this magazine on them. 

```json
%activity_mag_announce%
```
