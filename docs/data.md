# Datas

## user

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| pseudo | VARCHAR(30) | Index, not null | min: 3<br> max: 30<br> NotBlank |
| email | VARCHAR(180) | Index, not null, unique | min: 3<br> max: 180<br> EmailType<br> Notblank<br> Unique |
| roles | json | | |
| password | string | | |
| picture_path | VARCHAR(255) | Not null | Default `0.png` |
| is_verified | boolean | Not null | Default `False` |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## message

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| conversation_id | ENTITY | Foreign key | |
| user_id | ENTITY | Foreign key | |
| message | VARCHAR(255) | Not null | min: 5<br> max: 255<br> Notblank |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## conversation

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## status

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| conversation_id | ENTITY | Foreign key | |
| user_id | ENTITY | Foreign key | |
| status | VARCHAR(10) | Not null | Choices |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## user_report

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| user_id | ENTITY | Foreign key | |
| reported_user_id | ENTITY | Foreign key | |
| comment | VARCHAR(255) | Not null | min: 5<br> max: 255<br> Notblank |
| is_processed | boolean | Not null | Default False |
| is_important | boolean | Not null | Default False |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## bug_report

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| user_id | ENTITY | Foreign key | |
| url | VARCHAR(255) | Not null | min: 12<br> max: 255<br> UrlType |
| comment | VARCHAR(255) | Not null | min: 5<br> max: 255<br> Notblank |
| is_processed | boolean | Not null | Default False |
| is_important | boolean | Not null | Default False |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## ban

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| user_id | ENTITY | Foreign key | |
| email | VARCHAR(180) | Not null, unique | min: 3<br> max: 180<br> EmailType<br> Notblank<br> Unique |
| comment | VARCHAR(255) | Null | max: 255 |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## patch_note

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| id | int | Primary key, unsigned, not null, auto_increment | |
| user_id | ENTITY | Foreign key | |
| note | TEXT | Null | min: 5<br> max: 2000<br> Notblank |
| created_at | datetime | Not null | |
| updated_at | datetime | Null | |

## user_conversation

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| conversation_id | ENTITY | Primary key, index, unsigned, not null |
| user_id | ENTITY | Primary key, index, unsigned, not null |

## user_user (user blocks user)

| Field | Type | Specificities | Constraints |
|--|--|--|--|
| blocked_user_id | ENTITY | Primary key, index, unsigned, not null |
| blocker_user_id | ENTITY | Primary key, index, unsigned, not null |
