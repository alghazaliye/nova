// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'local_nova_db.dart';

// ignore_for_file: type=lint
class $LocalChatsTable extends LocalChats
    with TableInfo<$LocalChatsTable, LocalChat> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalChatsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _chatIdMeta = const VerificationMeta('chatId');
  @override
  late final GeneratedColumn<String> chatId = GeneratedColumn<String>(
    'chat_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('UNIQUE'),
  );
  static const VerificationMeta _convTypeMeta = const VerificationMeta(
    'convType',
  );
  @override
  late final GeneratedColumn<String> convType = GeneratedColumn<String>(
    'conv_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('private'),
  );
  static const VerificationMeta _titleMeta = const VerificationMeta('title');
  @override
  late final GeneratedColumn<String> title = GeneratedColumn<String>(
    'title',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _avatarMeta = const VerificationMeta('avatar');
  @override
  late final GeneratedColumn<String> avatar = GeneratedColumn<String>(
    'avatar',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _lastMessageIdMeta = const VerificationMeta(
    'lastMessageId',
  );
  @override
  late final GeneratedColumn<int> lastMessageId = GeneratedColumn<int>(
    'last_message_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _lastMessagePreviewMeta =
      const VerificationMeta('lastMessagePreview');
  @override
  late final GeneratedColumn<String> lastMessagePreview =
      GeneratedColumn<String>(
        'last_message_preview',
        aliasedName,
        false,
        type: DriftSqlType.string,
        requiredDuringInsert: false,
        defaultValue: const Constant(''),
      );
  static const VerificationMeta _lastMessageAtMeta = const VerificationMeta(
    'lastMessageAt',
  );
  @override
  late final GeneratedColumn<String> lastMessageAt = GeneratedColumn<String>(
    'last_message_at',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _unreadCountMeta = const VerificationMeta(
    'unreadCount',
  );
  @override
  late final GeneratedColumn<int> unreadCount = GeneratedColumn<int>(
    'unread_count',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _mutedMeta = const VerificationMeta('muted');
  @override
  late final GeneratedColumn<bool> muted = GeneratedColumn<bool>(
    'muted',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("muted" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _archivedMeta = const VerificationMeta(
    'archived',
  );
  @override
  late final GeneratedColumn<bool> archived = GeneratedColumn<bool>(
    'archived',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("archived" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _pinnedMeta = const VerificationMeta('pinned');
  @override
  late final GeneratedColumn<bool> pinned = GeneratedColumn<bool>(
    'pinned',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("pinned" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _isGroupMeta = const VerificationMeta(
    'isGroup',
  );
  @override
  late final GeneratedColumn<bool> isGroup = GeneratedColumn<bool>(
    'is_group',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("is_group" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _memberCountMeta = const VerificationMeta(
    'memberCount',
  );
  @override
  late final GeneratedColumn<int> memberCount = GeneratedColumn<int>(
    'member_count',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _otherUserIdMeta = const VerificationMeta(
    'otherUserId',
  );
  @override
  late final GeneratedColumn<int> otherUserId = GeneratedColumn<int>(
    'other_user_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _groupIdMeta = const VerificationMeta(
    'groupId',
  );
  @override
  late final GeneratedColumn<int> groupId = GeneratedColumn<int>(
    'group_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<String> updatedAt = GeneratedColumn<String>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _deletedForMeMeta = const VerificationMeta(
    'deletedForMe',
  );
  @override
  late final GeneratedColumn<int> deletedForMe = GeneratedColumn<int>(
    'deleted_for_me',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    chatId,
    convType,
    title,
    avatar,
    lastMessageId,
    lastMessagePreview,
    lastMessageAt,
    unreadCount,
    muted,
    archived,
    pinned,
    isGroup,
    memberCount,
    otherUserId,
    groupId,
    updatedAt,
    deletedForMe,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_chats';
  @override
  VerificationContext validateIntegrity(
    Insertable<LocalChat> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('chat_id')) {
      context.handle(
        _chatIdMeta,
        chatId.isAcceptableOrUnknown(data['chat_id']!, _chatIdMeta),
      );
    } else if (isInserting) {
      context.missing(_chatIdMeta);
    }
    if (data.containsKey('conv_type')) {
      context.handle(
        _convTypeMeta,
        convType.isAcceptableOrUnknown(data['conv_type']!, _convTypeMeta),
      );
    }
    if (data.containsKey('title')) {
      context.handle(
        _titleMeta,
        title.isAcceptableOrUnknown(data['title']!, _titleMeta),
      );
    }
    if (data.containsKey('avatar')) {
      context.handle(
        _avatarMeta,
        avatar.isAcceptableOrUnknown(data['avatar']!, _avatarMeta),
      );
    }
    if (data.containsKey('last_message_id')) {
      context.handle(
        _lastMessageIdMeta,
        lastMessageId.isAcceptableOrUnknown(
          data['last_message_id']!,
          _lastMessageIdMeta,
        ),
      );
    }
    if (data.containsKey('last_message_preview')) {
      context.handle(
        _lastMessagePreviewMeta,
        lastMessagePreview.isAcceptableOrUnknown(
          data['last_message_preview']!,
          _lastMessagePreviewMeta,
        ),
      );
    }
    if (data.containsKey('last_message_at')) {
      context.handle(
        _lastMessageAtMeta,
        lastMessageAt.isAcceptableOrUnknown(
          data['last_message_at']!,
          _lastMessageAtMeta,
        ),
      );
    }
    if (data.containsKey('unread_count')) {
      context.handle(
        _unreadCountMeta,
        unreadCount.isAcceptableOrUnknown(
          data['unread_count']!,
          _unreadCountMeta,
        ),
      );
    }
    if (data.containsKey('muted')) {
      context.handle(
        _mutedMeta,
        muted.isAcceptableOrUnknown(data['muted']!, _mutedMeta),
      );
    }
    if (data.containsKey('archived')) {
      context.handle(
        _archivedMeta,
        archived.isAcceptableOrUnknown(data['archived']!, _archivedMeta),
      );
    }
    if (data.containsKey('pinned')) {
      context.handle(
        _pinnedMeta,
        pinned.isAcceptableOrUnknown(data['pinned']!, _pinnedMeta),
      );
    }
    if (data.containsKey('is_group')) {
      context.handle(
        _isGroupMeta,
        isGroup.isAcceptableOrUnknown(data['is_group']!, _isGroupMeta),
      );
    }
    if (data.containsKey('member_count')) {
      context.handle(
        _memberCountMeta,
        memberCount.isAcceptableOrUnknown(
          data['member_count']!,
          _memberCountMeta,
        ),
      );
    }
    if (data.containsKey('other_user_id')) {
      context.handle(
        _otherUserIdMeta,
        otherUserId.isAcceptableOrUnknown(
          data['other_user_id']!,
          _otherUserIdMeta,
        ),
      );
    }
    if (data.containsKey('group_id')) {
      context.handle(
        _groupIdMeta,
        groupId.isAcceptableOrUnknown(data['group_id']!, _groupIdMeta),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    }
    if (data.containsKey('deleted_for_me')) {
      context.handle(
        _deletedForMeMeta,
        deletedForMe.isAcceptableOrUnknown(
          data['deleted_for_me']!,
          _deletedForMeMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  LocalChat map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalChat(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      chatId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}chat_id'],
      )!,
      convType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}conv_type'],
      )!,
      title: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}title'],
      )!,
      avatar: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}avatar'],
      ),
      lastMessageId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}last_message_id'],
      )!,
      lastMessagePreview: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_message_preview'],
      )!,
      lastMessageAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_message_at'],
      ),
      unreadCount: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}unread_count'],
      )!,
      muted: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}muted'],
      )!,
      archived: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}archived'],
      )!,
      pinned: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}pinned'],
      )!,
      isGroup: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}is_group'],
      )!,
      memberCount: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}member_count'],
      )!,
      otherUserId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}other_user_id'],
      )!,
      groupId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}group_id'],
      ),
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}updated_at'],
      )!,
      deletedForMe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}deleted_for_me'],
      )!,
    );
  }

  @override
  $LocalChatsTable createAlias(String alias) {
    return $LocalChatsTable(attachedDatabase, alias);
  }
}

class LocalChat extends DataClass implements Insertable<LocalChat> {
  final int id;
  final String chatId;
  final String convType;
  final String title;
  final String? avatar;
  final int lastMessageId;
  final String lastMessagePreview;
  final String? lastMessageAt;
  final int unreadCount;
  final bool muted;
  final bool archived;
  final bool pinned;
  final bool isGroup;
  final int memberCount;
  final int otherUserId;
  final int? groupId;
  final String updatedAt;
  final int deletedForMe;
  const LocalChat({
    required this.id,
    required this.chatId,
    required this.convType,
    required this.title,
    this.avatar,
    required this.lastMessageId,
    required this.lastMessagePreview,
    this.lastMessageAt,
    required this.unreadCount,
    required this.muted,
    required this.archived,
    required this.pinned,
    required this.isGroup,
    required this.memberCount,
    required this.otherUserId,
    this.groupId,
    required this.updatedAt,
    required this.deletedForMe,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['chat_id'] = Variable<String>(chatId);
    map['conv_type'] = Variable<String>(convType);
    map['title'] = Variable<String>(title);
    if (!nullToAbsent || avatar != null) {
      map['avatar'] = Variable<String>(avatar);
    }
    map['last_message_id'] = Variable<int>(lastMessageId);
    map['last_message_preview'] = Variable<String>(lastMessagePreview);
    if (!nullToAbsent || lastMessageAt != null) {
      map['last_message_at'] = Variable<String>(lastMessageAt);
    }
    map['unread_count'] = Variable<int>(unreadCount);
    map['muted'] = Variable<bool>(muted);
    map['archived'] = Variable<bool>(archived);
    map['pinned'] = Variable<bool>(pinned);
    map['is_group'] = Variable<bool>(isGroup);
    map['member_count'] = Variable<int>(memberCount);
    map['other_user_id'] = Variable<int>(otherUserId);
    if (!nullToAbsent || groupId != null) {
      map['group_id'] = Variable<int>(groupId);
    }
    map['updated_at'] = Variable<String>(updatedAt);
    map['deleted_for_me'] = Variable<int>(deletedForMe);
    return map;
  }

  LocalChatsCompanion toCompanion(bool nullToAbsent) {
    return LocalChatsCompanion(
      id: Value(id),
      chatId: Value(chatId),
      convType: Value(convType),
      title: Value(title),
      avatar: avatar == null && nullToAbsent
          ? const Value.absent()
          : Value(avatar),
      lastMessageId: Value(lastMessageId),
      lastMessagePreview: Value(lastMessagePreview),
      lastMessageAt: lastMessageAt == null && nullToAbsent
          ? const Value.absent()
          : Value(lastMessageAt),
      unreadCount: Value(unreadCount),
      muted: Value(muted),
      archived: Value(archived),
      pinned: Value(pinned),
      isGroup: Value(isGroup),
      memberCount: Value(memberCount),
      otherUserId: Value(otherUserId),
      groupId: groupId == null && nullToAbsent
          ? const Value.absent()
          : Value(groupId),
      updatedAt: Value(updatedAt),
      deletedForMe: Value(deletedForMe),
    );
  }

  factory LocalChat.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalChat(
      id: serializer.fromJson<int>(json['id']),
      chatId: serializer.fromJson<String>(json['chatId']),
      convType: serializer.fromJson<String>(json['convType']),
      title: serializer.fromJson<String>(json['title']),
      avatar: serializer.fromJson<String?>(json['avatar']),
      lastMessageId: serializer.fromJson<int>(json['lastMessageId']),
      lastMessagePreview: serializer.fromJson<String>(
        json['lastMessagePreview'],
      ),
      lastMessageAt: serializer.fromJson<String?>(json['lastMessageAt']),
      unreadCount: serializer.fromJson<int>(json['unreadCount']),
      muted: serializer.fromJson<bool>(json['muted']),
      archived: serializer.fromJson<bool>(json['archived']),
      pinned: serializer.fromJson<bool>(json['pinned']),
      isGroup: serializer.fromJson<bool>(json['isGroup']),
      memberCount: serializer.fromJson<int>(json['memberCount']),
      otherUserId: serializer.fromJson<int>(json['otherUserId']),
      groupId: serializer.fromJson<int?>(json['groupId']),
      updatedAt: serializer.fromJson<String>(json['updatedAt']),
      deletedForMe: serializer.fromJson<int>(json['deletedForMe']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'chatId': serializer.toJson<String>(chatId),
      'convType': serializer.toJson<String>(convType),
      'title': serializer.toJson<String>(title),
      'avatar': serializer.toJson<String?>(avatar),
      'lastMessageId': serializer.toJson<int>(lastMessageId),
      'lastMessagePreview': serializer.toJson<String>(lastMessagePreview),
      'lastMessageAt': serializer.toJson<String?>(lastMessageAt),
      'unreadCount': serializer.toJson<int>(unreadCount),
      'muted': serializer.toJson<bool>(muted),
      'archived': serializer.toJson<bool>(archived),
      'pinned': serializer.toJson<bool>(pinned),
      'isGroup': serializer.toJson<bool>(isGroup),
      'memberCount': serializer.toJson<int>(memberCount),
      'otherUserId': serializer.toJson<int>(otherUserId),
      'groupId': serializer.toJson<int?>(groupId),
      'updatedAt': serializer.toJson<String>(updatedAt),
      'deletedForMe': serializer.toJson<int>(deletedForMe),
    };
  }

  LocalChat copyWith({
    int? id,
    String? chatId,
    String? convType,
    String? title,
    Value<String?> avatar = const Value.absent(),
    int? lastMessageId,
    String? lastMessagePreview,
    Value<String?> lastMessageAt = const Value.absent(),
    int? unreadCount,
    bool? muted,
    bool? archived,
    bool? pinned,
    bool? isGroup,
    int? memberCount,
    int? otherUserId,
    Value<int?> groupId = const Value.absent(),
    String? updatedAt,
    int? deletedForMe,
  }) => LocalChat(
    id: id ?? this.id,
    chatId: chatId ?? this.chatId,
    convType: convType ?? this.convType,
    title: title ?? this.title,
    avatar: avatar.present ? avatar.value : this.avatar,
    lastMessageId: lastMessageId ?? this.lastMessageId,
    lastMessagePreview: lastMessagePreview ?? this.lastMessagePreview,
    lastMessageAt: lastMessageAt.present
        ? lastMessageAt.value
        : this.lastMessageAt,
    unreadCount: unreadCount ?? this.unreadCount,
    muted: muted ?? this.muted,
    archived: archived ?? this.archived,
    pinned: pinned ?? this.pinned,
    isGroup: isGroup ?? this.isGroup,
    memberCount: memberCount ?? this.memberCount,
    otherUserId: otherUserId ?? this.otherUserId,
    groupId: groupId.present ? groupId.value : this.groupId,
    updatedAt: updatedAt ?? this.updatedAt,
    deletedForMe: deletedForMe ?? this.deletedForMe,
  );
  LocalChat copyWithCompanion(LocalChatsCompanion data) {
    return LocalChat(
      id: data.id.present ? data.id.value : this.id,
      chatId: data.chatId.present ? data.chatId.value : this.chatId,
      convType: data.convType.present ? data.convType.value : this.convType,
      title: data.title.present ? data.title.value : this.title,
      avatar: data.avatar.present ? data.avatar.value : this.avatar,
      lastMessageId: data.lastMessageId.present
          ? data.lastMessageId.value
          : this.lastMessageId,
      lastMessagePreview: data.lastMessagePreview.present
          ? data.lastMessagePreview.value
          : this.lastMessagePreview,
      lastMessageAt: data.lastMessageAt.present
          ? data.lastMessageAt.value
          : this.lastMessageAt,
      unreadCount: data.unreadCount.present
          ? data.unreadCount.value
          : this.unreadCount,
      muted: data.muted.present ? data.muted.value : this.muted,
      archived: data.archived.present ? data.archived.value : this.archived,
      pinned: data.pinned.present ? data.pinned.value : this.pinned,
      isGroup: data.isGroup.present ? data.isGroup.value : this.isGroup,
      memberCount: data.memberCount.present
          ? data.memberCount.value
          : this.memberCount,
      otherUserId: data.otherUserId.present
          ? data.otherUserId.value
          : this.otherUserId,
      groupId: data.groupId.present ? data.groupId.value : this.groupId,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
      deletedForMe: data.deletedForMe.present
          ? data.deletedForMe.value
          : this.deletedForMe,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LocalChat(')
          ..write('id: $id, ')
          ..write('chatId: $chatId, ')
          ..write('convType: $convType, ')
          ..write('title: $title, ')
          ..write('avatar: $avatar, ')
          ..write('lastMessageId: $lastMessageId, ')
          ..write('lastMessagePreview: $lastMessagePreview, ')
          ..write('lastMessageAt: $lastMessageAt, ')
          ..write('unreadCount: $unreadCount, ')
          ..write('muted: $muted, ')
          ..write('archived: $archived, ')
          ..write('pinned: $pinned, ')
          ..write('isGroup: $isGroup, ')
          ..write('memberCount: $memberCount, ')
          ..write('otherUserId: $otherUserId, ')
          ..write('groupId: $groupId, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('deletedForMe: $deletedForMe')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    chatId,
    convType,
    title,
    avatar,
    lastMessageId,
    lastMessagePreview,
    lastMessageAt,
    unreadCount,
    muted,
    archived,
    pinned,
    isGroup,
    memberCount,
    otherUserId,
    groupId,
    updatedAt,
    deletedForMe,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalChat &&
          other.id == this.id &&
          other.chatId == this.chatId &&
          other.convType == this.convType &&
          other.title == this.title &&
          other.avatar == this.avatar &&
          other.lastMessageId == this.lastMessageId &&
          other.lastMessagePreview == this.lastMessagePreview &&
          other.lastMessageAt == this.lastMessageAt &&
          other.unreadCount == this.unreadCount &&
          other.muted == this.muted &&
          other.archived == this.archived &&
          other.pinned == this.pinned &&
          other.isGroup == this.isGroup &&
          other.memberCount == this.memberCount &&
          other.otherUserId == this.otherUserId &&
          other.groupId == this.groupId &&
          other.updatedAt == this.updatedAt &&
          other.deletedForMe == this.deletedForMe);
}

class LocalChatsCompanion extends UpdateCompanion<LocalChat> {
  final Value<int> id;
  final Value<String> chatId;
  final Value<String> convType;
  final Value<String> title;
  final Value<String?> avatar;
  final Value<int> lastMessageId;
  final Value<String> lastMessagePreview;
  final Value<String?> lastMessageAt;
  final Value<int> unreadCount;
  final Value<bool> muted;
  final Value<bool> archived;
  final Value<bool> pinned;
  final Value<bool> isGroup;
  final Value<int> memberCount;
  final Value<int> otherUserId;
  final Value<int?> groupId;
  final Value<String> updatedAt;
  final Value<int> deletedForMe;
  const LocalChatsCompanion({
    this.id = const Value.absent(),
    this.chatId = const Value.absent(),
    this.convType = const Value.absent(),
    this.title = const Value.absent(),
    this.avatar = const Value.absent(),
    this.lastMessageId = const Value.absent(),
    this.lastMessagePreview = const Value.absent(),
    this.lastMessageAt = const Value.absent(),
    this.unreadCount = const Value.absent(),
    this.muted = const Value.absent(),
    this.archived = const Value.absent(),
    this.pinned = const Value.absent(),
    this.isGroup = const Value.absent(),
    this.memberCount = const Value.absent(),
    this.otherUserId = const Value.absent(),
    this.groupId = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.deletedForMe = const Value.absent(),
  });
  LocalChatsCompanion.insert({
    this.id = const Value.absent(),
    required String chatId,
    this.convType = const Value.absent(),
    this.title = const Value.absent(),
    this.avatar = const Value.absent(),
    this.lastMessageId = const Value.absent(),
    this.lastMessagePreview = const Value.absent(),
    this.lastMessageAt = const Value.absent(),
    this.unreadCount = const Value.absent(),
    this.muted = const Value.absent(),
    this.archived = const Value.absent(),
    this.pinned = const Value.absent(),
    this.isGroup = const Value.absent(),
    this.memberCount = const Value.absent(),
    this.otherUserId = const Value.absent(),
    this.groupId = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.deletedForMe = const Value.absent(),
  }) : chatId = Value(chatId);
  static Insertable<LocalChat> custom({
    Expression<int>? id,
    Expression<String>? chatId,
    Expression<String>? convType,
    Expression<String>? title,
    Expression<String>? avatar,
    Expression<int>? lastMessageId,
    Expression<String>? lastMessagePreview,
    Expression<String>? lastMessageAt,
    Expression<int>? unreadCount,
    Expression<bool>? muted,
    Expression<bool>? archived,
    Expression<bool>? pinned,
    Expression<bool>? isGroup,
    Expression<int>? memberCount,
    Expression<int>? otherUserId,
    Expression<int>? groupId,
    Expression<String>? updatedAt,
    Expression<int>? deletedForMe,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (chatId != null) 'chat_id': chatId,
      if (convType != null) 'conv_type': convType,
      if (title != null) 'title': title,
      if (avatar != null) 'avatar': avatar,
      if (lastMessageId != null) 'last_message_id': lastMessageId,
      if (lastMessagePreview != null)
        'last_message_preview': lastMessagePreview,
      if (lastMessageAt != null) 'last_message_at': lastMessageAt,
      if (unreadCount != null) 'unread_count': unreadCount,
      if (muted != null) 'muted': muted,
      if (archived != null) 'archived': archived,
      if (pinned != null) 'pinned': pinned,
      if (isGroup != null) 'is_group': isGroup,
      if (memberCount != null) 'member_count': memberCount,
      if (otherUserId != null) 'other_user_id': otherUserId,
      if (groupId != null) 'group_id': groupId,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (deletedForMe != null) 'deleted_for_me': deletedForMe,
    });
  }

  LocalChatsCompanion copyWith({
    Value<int>? id,
    Value<String>? chatId,
    Value<String>? convType,
    Value<String>? title,
    Value<String?>? avatar,
    Value<int>? lastMessageId,
    Value<String>? lastMessagePreview,
    Value<String?>? lastMessageAt,
    Value<int>? unreadCount,
    Value<bool>? muted,
    Value<bool>? archived,
    Value<bool>? pinned,
    Value<bool>? isGroup,
    Value<int>? memberCount,
    Value<int>? otherUserId,
    Value<int?>? groupId,
    Value<String>? updatedAt,
    Value<int>? deletedForMe,
  }) {
    return LocalChatsCompanion(
      id: id ?? this.id,
      chatId: chatId ?? this.chatId,
      convType: convType ?? this.convType,
      title: title ?? this.title,
      avatar: avatar ?? this.avatar,
      lastMessageId: lastMessageId ?? this.lastMessageId,
      lastMessagePreview: lastMessagePreview ?? this.lastMessagePreview,
      lastMessageAt: lastMessageAt ?? this.lastMessageAt,
      unreadCount: unreadCount ?? this.unreadCount,
      muted: muted ?? this.muted,
      archived: archived ?? this.archived,
      pinned: pinned ?? this.pinned,
      isGroup: isGroup ?? this.isGroup,
      memberCount: memberCount ?? this.memberCount,
      otherUserId: otherUserId ?? this.otherUserId,
      groupId: groupId ?? this.groupId,
      updatedAt: updatedAt ?? this.updatedAt,
      deletedForMe: deletedForMe ?? this.deletedForMe,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (chatId.present) {
      map['chat_id'] = Variable<String>(chatId.value);
    }
    if (convType.present) {
      map['conv_type'] = Variable<String>(convType.value);
    }
    if (title.present) {
      map['title'] = Variable<String>(title.value);
    }
    if (avatar.present) {
      map['avatar'] = Variable<String>(avatar.value);
    }
    if (lastMessageId.present) {
      map['last_message_id'] = Variable<int>(lastMessageId.value);
    }
    if (lastMessagePreview.present) {
      map['last_message_preview'] = Variable<String>(lastMessagePreview.value);
    }
    if (lastMessageAt.present) {
      map['last_message_at'] = Variable<String>(lastMessageAt.value);
    }
    if (unreadCount.present) {
      map['unread_count'] = Variable<int>(unreadCount.value);
    }
    if (muted.present) {
      map['muted'] = Variable<bool>(muted.value);
    }
    if (archived.present) {
      map['archived'] = Variable<bool>(archived.value);
    }
    if (pinned.present) {
      map['pinned'] = Variable<bool>(pinned.value);
    }
    if (isGroup.present) {
      map['is_group'] = Variable<bool>(isGroup.value);
    }
    if (memberCount.present) {
      map['member_count'] = Variable<int>(memberCount.value);
    }
    if (otherUserId.present) {
      map['other_user_id'] = Variable<int>(otherUserId.value);
    }
    if (groupId.present) {
      map['group_id'] = Variable<int>(groupId.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<String>(updatedAt.value);
    }
    if (deletedForMe.present) {
      map['deleted_for_me'] = Variable<int>(deletedForMe.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalChatsCompanion(')
          ..write('id: $id, ')
          ..write('chatId: $chatId, ')
          ..write('convType: $convType, ')
          ..write('title: $title, ')
          ..write('avatar: $avatar, ')
          ..write('lastMessageId: $lastMessageId, ')
          ..write('lastMessagePreview: $lastMessagePreview, ')
          ..write('lastMessageAt: $lastMessageAt, ')
          ..write('unreadCount: $unreadCount, ')
          ..write('muted: $muted, ')
          ..write('archived: $archived, ')
          ..write('pinned: $pinned, ')
          ..write('isGroup: $isGroup, ')
          ..write('memberCount: $memberCount, ')
          ..write('otherUserId: $otherUserId, ')
          ..write('groupId: $groupId, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('deletedForMe: $deletedForMe')
          ..write(')'))
        .toString();
  }
}

class $LocalMessagesTable extends LocalMessages
    with TableInfo<$LocalMessagesTable, LocalMessage> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalMessagesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'PRIMARY KEY AUTOINCREMENT',
    ),
  );
  static const VerificationMeta _serverIdMeta = const VerificationMeta(
    'serverId',
  );
  @override
  late final GeneratedColumn<int> serverId = GeneratedColumn<int>(
    'server_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _conversationIdMeta = const VerificationMeta(
    'conversationId',
  );
  @override
  late final GeneratedColumn<int> conversationId = GeneratedColumn<int>(
    'conversation_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _localUuidMeta = const VerificationMeta(
    'localUuid',
  );
  @override
  late final GeneratedColumn<String> localUuid = GeneratedColumn<String>(
    'local_uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('UNIQUE'),
  );
  static const VerificationMeta _senderIdMeta = const VerificationMeta(
    'senderId',
  );
  @override
  late final GeneratedColumn<int> senderId = GeneratedColumn<int>(
    'sender_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _messageTypeMeta = const VerificationMeta(
    'messageType',
  );
  @override
  late final GeneratedColumn<String> messageType = GeneratedColumn<String>(
    'message_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('text'),
  );
  static const VerificationMeta _bodyTextMeta = const VerificationMeta(
    'bodyText',
  );
  @override
  late final GeneratedColumn<String> bodyText = GeneratedColumn<String>(
    'body_text',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _replyToServerIdMeta = const VerificationMeta(
    'replyToServerId',
  );
  @override
  late final GeneratedColumn<int> replyToServerId = GeneratedColumn<int>(
    'reply_to_server_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _replyToLocalUuidMeta = const VerificationMeta(
    'replyToLocalUuid',
  );
  @override
  late final GeneratedColumn<int> replyToLocalUuid = GeneratedColumn<int>(
    'reply_to_local_uuid',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _mediaLocalIdMeta = const VerificationMeta(
    'mediaLocalId',
  );
  @override
  late final GeneratedColumn<int> mediaLocalId = GeneratedColumn<int>(
    'media_local_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _filePathMeta = const VerificationMeta(
    'filePath',
  );
  @override
  late final GeneratedColumn<String> filePath = GeneratedColumn<String>(
    'file_path',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _thumbnailPathMeta = const VerificationMeta(
    'thumbnailPath',
  );
  @override
  late final GeneratedColumn<String> thumbnailPath = GeneratedColumn<String>(
    'thumbnail_path',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _mimeTypeMeta = const VerificationMeta(
    'mimeType',
  );
  @override
  late final GeneratedColumn<String> mimeType = GeneratedColumn<String>(
    'mime_type',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _fileSizeMeta = const VerificationMeta(
    'fileSize',
  );
  @override
  late final GeneratedColumn<int> fileSize = GeneratedColumn<int>(
    'file_size',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _widthMeta = const VerificationMeta('width');
  @override
  late final GeneratedColumn<int> width = GeneratedColumn<int>(
    'width',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _heightMeta = const VerificationMeta('height');
  @override
  late final GeneratedColumn<int> height = GeneratedColumn<int>(
    'height',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _durationMeta = const VerificationMeta(
    'duration',
  );
  @override
  late final GeneratedColumn<int> duration = GeneratedColumn<int>(
    'duration',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _serverTimestampMeta = const VerificationMeta(
    'serverTimestamp',
  );
  @override
  late final GeneratedColumn<String> serverTimestamp = GeneratedColumn<String>(
    'server_timestamp',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _localCreatedAtMeta = const VerificationMeta(
    'localCreatedAt',
  );
  @override
  late final GeneratedColumn<String> localCreatedAt = GeneratedColumn<String>(
    'local_created_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending_sync'),
  );
  static const VerificationMeta _syncStatusMeta = const VerificationMeta(
    'syncStatus',
  );
  @override
  late final GeneratedColumn<String> syncStatus = GeneratedColumn<String>(
    'sync_status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );
  static const VerificationMeta _deletedForMeMeta = const VerificationMeta(
    'deletedForMe',
  );
  @override
  late final GeneratedColumn<int> deletedForMe = GeneratedColumn<int>(
    'deleted_for_me',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _deletedForAllMeta = const VerificationMeta(
    'deletedForAll',
  );
  @override
  late final GeneratedColumn<int> deletedForAll = GeneratedColumn<int>(
    'deleted_for_all',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _isEditedMeta = const VerificationMeta(
    'isEdited',
  );
  @override
  late final GeneratedColumn<int> isEdited = GeneratedColumn<int>(
    'is_edited',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _editedAtMeta = const VerificationMeta(
    'editedAt',
  );
  @override
  late final GeneratedColumn<String> editedAt = GeneratedColumn<String>(
    'edited_at',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _attemptMeta = const VerificationMeta(
    'attempt',
  );
  @override
  late final GeneratedColumn<int> attempt = GeneratedColumn<int>(
    'attempt',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    serverId,
    conversationId,
    localUuid,
    senderId,
    messageType,
    bodyText,
    replyToServerId,
    replyToLocalUuid,
    mediaLocalId,
    filePath,
    thumbnailPath,
    mimeType,
    fileSize,
    width,
    height,
    duration,
    serverTimestamp,
    localCreatedAt,
    status,
    syncStatus,
    deletedForMe,
    deletedForAll,
    isEdited,
    editedAt,
    attempt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_messages';
  @override
  VerificationContext validateIntegrity(
    Insertable<LocalMessage> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('server_id')) {
      context.handle(
        _serverIdMeta,
        serverId.isAcceptableOrUnknown(data['server_id']!, _serverIdMeta),
      );
    }
    if (data.containsKey('conversation_id')) {
      context.handle(
        _conversationIdMeta,
        conversationId.isAcceptableOrUnknown(
          data['conversation_id']!,
          _conversationIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_conversationIdMeta);
    }
    if (data.containsKey('local_uuid')) {
      context.handle(
        _localUuidMeta,
        localUuid.isAcceptableOrUnknown(data['local_uuid']!, _localUuidMeta),
      );
    } else if (isInserting) {
      context.missing(_localUuidMeta);
    }
    if (data.containsKey('sender_id')) {
      context.handle(
        _senderIdMeta,
        senderId.isAcceptableOrUnknown(data['sender_id']!, _senderIdMeta),
      );
    } else if (isInserting) {
      context.missing(_senderIdMeta);
    }
    if (data.containsKey('message_type')) {
      context.handle(
        _messageTypeMeta,
        messageType.isAcceptableOrUnknown(
          data['message_type']!,
          _messageTypeMeta,
        ),
      );
    }
    if (data.containsKey('body_text')) {
      context.handle(
        _bodyTextMeta,
        bodyText.isAcceptableOrUnknown(data['body_text']!, _bodyTextMeta),
      );
    }
    if (data.containsKey('reply_to_server_id')) {
      context.handle(
        _replyToServerIdMeta,
        replyToServerId.isAcceptableOrUnknown(
          data['reply_to_server_id']!,
          _replyToServerIdMeta,
        ),
      );
    }
    if (data.containsKey('reply_to_local_uuid')) {
      context.handle(
        _replyToLocalUuidMeta,
        replyToLocalUuid.isAcceptableOrUnknown(
          data['reply_to_local_uuid']!,
          _replyToLocalUuidMeta,
        ),
      );
    }
    if (data.containsKey('media_local_id')) {
      context.handle(
        _mediaLocalIdMeta,
        mediaLocalId.isAcceptableOrUnknown(
          data['media_local_id']!,
          _mediaLocalIdMeta,
        ),
      );
    }
    if (data.containsKey('file_path')) {
      context.handle(
        _filePathMeta,
        filePath.isAcceptableOrUnknown(data['file_path']!, _filePathMeta),
      );
    }
    if (data.containsKey('thumbnail_path')) {
      context.handle(
        _thumbnailPathMeta,
        thumbnailPath.isAcceptableOrUnknown(
          data['thumbnail_path']!,
          _thumbnailPathMeta,
        ),
      );
    }
    if (data.containsKey('mime_type')) {
      context.handle(
        _mimeTypeMeta,
        mimeType.isAcceptableOrUnknown(data['mime_type']!, _mimeTypeMeta),
      );
    }
    if (data.containsKey('file_size')) {
      context.handle(
        _fileSizeMeta,
        fileSize.isAcceptableOrUnknown(data['file_size']!, _fileSizeMeta),
      );
    }
    if (data.containsKey('width')) {
      context.handle(
        _widthMeta,
        width.isAcceptableOrUnknown(data['width']!, _widthMeta),
      );
    }
    if (data.containsKey('height')) {
      context.handle(
        _heightMeta,
        height.isAcceptableOrUnknown(data['height']!, _heightMeta),
      );
    }
    if (data.containsKey('duration')) {
      context.handle(
        _durationMeta,
        duration.isAcceptableOrUnknown(data['duration']!, _durationMeta),
      );
    }
    if (data.containsKey('server_timestamp')) {
      context.handle(
        _serverTimestampMeta,
        serverTimestamp.isAcceptableOrUnknown(
          data['server_timestamp']!,
          _serverTimestampMeta,
        ),
      );
    }
    if (data.containsKey('local_created_at')) {
      context.handle(
        _localCreatedAtMeta,
        localCreatedAt.isAcceptableOrUnknown(
          data['local_created_at']!,
          _localCreatedAtMeta,
        ),
      );
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('sync_status')) {
      context.handle(
        _syncStatusMeta,
        syncStatus.isAcceptableOrUnknown(data['sync_status']!, _syncStatusMeta),
      );
    }
    if (data.containsKey('deleted_for_me')) {
      context.handle(
        _deletedForMeMeta,
        deletedForMe.isAcceptableOrUnknown(
          data['deleted_for_me']!,
          _deletedForMeMeta,
        ),
      );
    }
    if (data.containsKey('deleted_for_all')) {
      context.handle(
        _deletedForAllMeta,
        deletedForAll.isAcceptableOrUnknown(
          data['deleted_for_all']!,
          _deletedForAllMeta,
        ),
      );
    }
    if (data.containsKey('is_edited')) {
      context.handle(
        _isEditedMeta,
        isEdited.isAcceptableOrUnknown(data['is_edited']!, _isEditedMeta),
      );
    }
    if (data.containsKey('edited_at')) {
      context.handle(
        _editedAtMeta,
        editedAt.isAcceptableOrUnknown(data['edited_at']!, _editedAtMeta),
      );
    }
    if (data.containsKey('attempt')) {
      context.handle(
        _attemptMeta,
        attempt.isAcceptableOrUnknown(data['attempt']!, _attemptMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  LocalMessage map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalMessage(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      serverId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}server_id'],
      ),
      conversationId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}conversation_id'],
      )!,
      localUuid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_uuid'],
      )!,
      senderId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}sender_id'],
      )!,
      messageType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}message_type'],
      )!,
      bodyText: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}body_text'],
      ),
      replyToServerId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}reply_to_server_id'],
      ),
      replyToLocalUuid: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}reply_to_local_uuid'],
      ),
      mediaLocalId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}media_local_id'],
      ),
      filePath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}file_path'],
      ),
      thumbnailPath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}thumbnail_path'],
      ),
      mimeType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}mime_type'],
      ),
      fileSize: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}file_size'],
      ),
      width: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}width'],
      ),
      height: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}height'],
      ),
      duration: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}duration'],
      ),
      serverTimestamp: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}server_timestamp'],
      )!,
      localCreatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_created_at'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      syncStatus: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}sync_status'],
      )!,
      deletedForMe: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}deleted_for_me'],
      )!,
      deletedForAll: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}deleted_for_all'],
      )!,
      isEdited: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}is_edited'],
      )!,
      editedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}edited_at'],
      ),
      attempt: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempt'],
      )!,
    );
  }

  @override
  $LocalMessagesTable createAlias(String alias) {
    return $LocalMessagesTable(attachedDatabase, alias);
  }
}

class LocalMessage extends DataClass implements Insertable<LocalMessage> {
  final int id;
  final int? serverId;
  final int conversationId;
  final String localUuid;
  final int senderId;
  final String messageType;
  final String? bodyText;
  final int? replyToServerId;
  final int? replyToLocalUuid;
  final int? mediaLocalId;
  final String? filePath;
  final String? thumbnailPath;
  final String? mimeType;
  final int? fileSize;
  final int? width;
  final int? height;
  final int? duration;
  final String serverTimestamp;
  final String localCreatedAt;
  final String status;
  final String syncStatus;
  final int deletedForMe;
  final int deletedForAll;
  final int isEdited;
  final String? editedAt;
  final int attempt;
  const LocalMessage({
    required this.id,
    this.serverId,
    required this.conversationId,
    required this.localUuid,
    required this.senderId,
    required this.messageType,
    this.bodyText,
    this.replyToServerId,
    this.replyToLocalUuid,
    this.mediaLocalId,
    this.filePath,
    this.thumbnailPath,
    this.mimeType,
    this.fileSize,
    this.width,
    this.height,
    this.duration,
    required this.serverTimestamp,
    required this.localCreatedAt,
    required this.status,
    required this.syncStatus,
    required this.deletedForMe,
    required this.deletedForAll,
    required this.isEdited,
    this.editedAt,
    required this.attempt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    if (!nullToAbsent || serverId != null) {
      map['server_id'] = Variable<int>(serverId);
    }
    map['conversation_id'] = Variable<int>(conversationId);
    map['local_uuid'] = Variable<String>(localUuid);
    map['sender_id'] = Variable<int>(senderId);
    map['message_type'] = Variable<String>(messageType);
    if (!nullToAbsent || bodyText != null) {
      map['body_text'] = Variable<String>(bodyText);
    }
    if (!nullToAbsent || replyToServerId != null) {
      map['reply_to_server_id'] = Variable<int>(replyToServerId);
    }
    if (!nullToAbsent || replyToLocalUuid != null) {
      map['reply_to_local_uuid'] = Variable<int>(replyToLocalUuid);
    }
    if (!nullToAbsent || mediaLocalId != null) {
      map['media_local_id'] = Variable<int>(mediaLocalId);
    }
    if (!nullToAbsent || filePath != null) {
      map['file_path'] = Variable<String>(filePath);
    }
    if (!nullToAbsent || thumbnailPath != null) {
      map['thumbnail_path'] = Variable<String>(thumbnailPath);
    }
    if (!nullToAbsent || mimeType != null) {
      map['mime_type'] = Variable<String>(mimeType);
    }
    if (!nullToAbsent || fileSize != null) {
      map['file_size'] = Variable<int>(fileSize);
    }
    if (!nullToAbsent || width != null) {
      map['width'] = Variable<int>(width);
    }
    if (!nullToAbsent || height != null) {
      map['height'] = Variable<int>(height);
    }
    if (!nullToAbsent || duration != null) {
      map['duration'] = Variable<int>(duration);
    }
    map['server_timestamp'] = Variable<String>(serverTimestamp);
    map['local_created_at'] = Variable<String>(localCreatedAt);
    map['status'] = Variable<String>(status);
    map['sync_status'] = Variable<String>(syncStatus);
    map['deleted_for_me'] = Variable<int>(deletedForMe);
    map['deleted_for_all'] = Variable<int>(deletedForAll);
    map['is_edited'] = Variable<int>(isEdited);
    if (!nullToAbsent || editedAt != null) {
      map['edited_at'] = Variable<String>(editedAt);
    }
    map['attempt'] = Variable<int>(attempt);
    return map;
  }

  LocalMessagesCompanion toCompanion(bool nullToAbsent) {
    return LocalMessagesCompanion(
      id: Value(id),
      serverId: serverId == null && nullToAbsent
          ? const Value.absent()
          : Value(serverId),
      conversationId: Value(conversationId),
      localUuid: Value(localUuid),
      senderId: Value(senderId),
      messageType: Value(messageType),
      bodyText: bodyText == null && nullToAbsent
          ? const Value.absent()
          : Value(bodyText),
      replyToServerId: replyToServerId == null && nullToAbsent
          ? const Value.absent()
          : Value(replyToServerId),
      replyToLocalUuid: replyToLocalUuid == null && nullToAbsent
          ? const Value.absent()
          : Value(replyToLocalUuid),
      mediaLocalId: mediaLocalId == null && nullToAbsent
          ? const Value.absent()
          : Value(mediaLocalId),
      filePath: filePath == null && nullToAbsent
          ? const Value.absent()
          : Value(filePath),
      thumbnailPath: thumbnailPath == null && nullToAbsent
          ? const Value.absent()
          : Value(thumbnailPath),
      mimeType: mimeType == null && nullToAbsent
          ? const Value.absent()
          : Value(mimeType),
      fileSize: fileSize == null && nullToAbsent
          ? const Value.absent()
          : Value(fileSize),
      width: width == null && nullToAbsent
          ? const Value.absent()
          : Value(width),
      height: height == null && nullToAbsent
          ? const Value.absent()
          : Value(height),
      duration: duration == null && nullToAbsent
          ? const Value.absent()
          : Value(duration),
      serverTimestamp: Value(serverTimestamp),
      localCreatedAt: Value(localCreatedAt),
      status: Value(status),
      syncStatus: Value(syncStatus),
      deletedForMe: Value(deletedForMe),
      deletedForAll: Value(deletedForAll),
      isEdited: Value(isEdited),
      editedAt: editedAt == null && nullToAbsent
          ? const Value.absent()
          : Value(editedAt),
      attempt: Value(attempt),
    );
  }

  factory LocalMessage.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalMessage(
      id: serializer.fromJson<int>(json['id']),
      serverId: serializer.fromJson<int?>(json['serverId']),
      conversationId: serializer.fromJson<int>(json['conversationId']),
      localUuid: serializer.fromJson<String>(json['localUuid']),
      senderId: serializer.fromJson<int>(json['senderId']),
      messageType: serializer.fromJson<String>(json['messageType']),
      bodyText: serializer.fromJson<String?>(json['bodyText']),
      replyToServerId: serializer.fromJson<int?>(json['replyToServerId']),
      replyToLocalUuid: serializer.fromJson<int?>(json['replyToLocalUuid']),
      mediaLocalId: serializer.fromJson<int?>(json['mediaLocalId']),
      filePath: serializer.fromJson<String?>(json['filePath']),
      thumbnailPath: serializer.fromJson<String?>(json['thumbnailPath']),
      mimeType: serializer.fromJson<String?>(json['mimeType']),
      fileSize: serializer.fromJson<int?>(json['fileSize']),
      width: serializer.fromJson<int?>(json['width']),
      height: serializer.fromJson<int?>(json['height']),
      duration: serializer.fromJson<int?>(json['duration']),
      serverTimestamp: serializer.fromJson<String>(json['serverTimestamp']),
      localCreatedAt: serializer.fromJson<String>(json['localCreatedAt']),
      status: serializer.fromJson<String>(json['status']),
      syncStatus: serializer.fromJson<String>(json['syncStatus']),
      deletedForMe: serializer.fromJson<int>(json['deletedForMe']),
      deletedForAll: serializer.fromJson<int>(json['deletedForAll']),
      isEdited: serializer.fromJson<int>(json['isEdited']),
      editedAt: serializer.fromJson<String?>(json['editedAt']),
      attempt: serializer.fromJson<int>(json['attempt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'serverId': serializer.toJson<int?>(serverId),
      'conversationId': serializer.toJson<int>(conversationId),
      'localUuid': serializer.toJson<String>(localUuid),
      'senderId': serializer.toJson<int>(senderId),
      'messageType': serializer.toJson<String>(messageType),
      'bodyText': serializer.toJson<String?>(bodyText),
      'replyToServerId': serializer.toJson<int?>(replyToServerId),
      'replyToLocalUuid': serializer.toJson<int?>(replyToLocalUuid),
      'mediaLocalId': serializer.toJson<int?>(mediaLocalId),
      'filePath': serializer.toJson<String?>(filePath),
      'thumbnailPath': serializer.toJson<String?>(thumbnailPath),
      'mimeType': serializer.toJson<String?>(mimeType),
      'fileSize': serializer.toJson<int?>(fileSize),
      'width': serializer.toJson<int?>(width),
      'height': serializer.toJson<int?>(height),
      'duration': serializer.toJson<int?>(duration),
      'serverTimestamp': serializer.toJson<String>(serverTimestamp),
      'localCreatedAt': serializer.toJson<String>(localCreatedAt),
      'status': serializer.toJson<String>(status),
      'syncStatus': serializer.toJson<String>(syncStatus),
      'deletedForMe': serializer.toJson<int>(deletedForMe),
      'deletedForAll': serializer.toJson<int>(deletedForAll),
      'isEdited': serializer.toJson<int>(isEdited),
      'editedAt': serializer.toJson<String?>(editedAt),
      'attempt': serializer.toJson<int>(attempt),
    };
  }

  LocalMessage copyWith({
    int? id,
    Value<int?> serverId = const Value.absent(),
    int? conversationId,
    String? localUuid,
    int? senderId,
    String? messageType,
    Value<String?> bodyText = const Value.absent(),
    Value<int?> replyToServerId = const Value.absent(),
    Value<int?> replyToLocalUuid = const Value.absent(),
    Value<int?> mediaLocalId = const Value.absent(),
    Value<String?> filePath = const Value.absent(),
    Value<String?> thumbnailPath = const Value.absent(),
    Value<String?> mimeType = const Value.absent(),
    Value<int?> fileSize = const Value.absent(),
    Value<int?> width = const Value.absent(),
    Value<int?> height = const Value.absent(),
    Value<int?> duration = const Value.absent(),
    String? serverTimestamp,
    String? localCreatedAt,
    String? status,
    String? syncStatus,
    int? deletedForMe,
    int? deletedForAll,
    int? isEdited,
    Value<String?> editedAt = const Value.absent(),
    int? attempt,
  }) => LocalMessage(
    id: id ?? this.id,
    serverId: serverId.present ? serverId.value : this.serverId,
    conversationId: conversationId ?? this.conversationId,
    localUuid: localUuid ?? this.localUuid,
    senderId: senderId ?? this.senderId,
    messageType: messageType ?? this.messageType,
    bodyText: bodyText.present ? bodyText.value : this.bodyText,
    replyToServerId: replyToServerId.present
        ? replyToServerId.value
        : this.replyToServerId,
    replyToLocalUuid: replyToLocalUuid.present
        ? replyToLocalUuid.value
        : this.replyToLocalUuid,
    mediaLocalId: mediaLocalId.present ? mediaLocalId.value : this.mediaLocalId,
    filePath: filePath.present ? filePath.value : this.filePath,
    thumbnailPath: thumbnailPath.present
        ? thumbnailPath.value
        : this.thumbnailPath,
    mimeType: mimeType.present ? mimeType.value : this.mimeType,
    fileSize: fileSize.present ? fileSize.value : this.fileSize,
    width: width.present ? width.value : this.width,
    height: height.present ? height.value : this.height,
    duration: duration.present ? duration.value : this.duration,
    serverTimestamp: serverTimestamp ?? this.serverTimestamp,
    localCreatedAt: localCreatedAt ?? this.localCreatedAt,
    status: status ?? this.status,
    syncStatus: syncStatus ?? this.syncStatus,
    deletedForMe: deletedForMe ?? this.deletedForMe,
    deletedForAll: deletedForAll ?? this.deletedForAll,
    isEdited: isEdited ?? this.isEdited,
    editedAt: editedAt.present ? editedAt.value : this.editedAt,
    attempt: attempt ?? this.attempt,
  );
  LocalMessage copyWithCompanion(LocalMessagesCompanion data) {
    return LocalMessage(
      id: data.id.present ? data.id.value : this.id,
      serverId: data.serverId.present ? data.serverId.value : this.serverId,
      conversationId: data.conversationId.present
          ? data.conversationId.value
          : this.conversationId,
      localUuid: data.localUuid.present ? data.localUuid.value : this.localUuid,
      senderId: data.senderId.present ? data.senderId.value : this.senderId,
      messageType: data.messageType.present
          ? data.messageType.value
          : this.messageType,
      bodyText: data.bodyText.present ? data.bodyText.value : this.bodyText,
      replyToServerId: data.replyToServerId.present
          ? data.replyToServerId.value
          : this.replyToServerId,
      replyToLocalUuid: data.replyToLocalUuid.present
          ? data.replyToLocalUuid.value
          : this.replyToLocalUuid,
      mediaLocalId: data.mediaLocalId.present
          ? data.mediaLocalId.value
          : this.mediaLocalId,
      filePath: data.filePath.present ? data.filePath.value : this.filePath,
      thumbnailPath: data.thumbnailPath.present
          ? data.thumbnailPath.value
          : this.thumbnailPath,
      mimeType: data.mimeType.present ? data.mimeType.value : this.mimeType,
      fileSize: data.fileSize.present ? data.fileSize.value : this.fileSize,
      width: data.width.present ? data.width.value : this.width,
      height: data.height.present ? data.height.value : this.height,
      duration: data.duration.present ? data.duration.value : this.duration,
      serverTimestamp: data.serverTimestamp.present
          ? data.serverTimestamp.value
          : this.serverTimestamp,
      localCreatedAt: data.localCreatedAt.present
          ? data.localCreatedAt.value
          : this.localCreatedAt,
      status: data.status.present ? data.status.value : this.status,
      syncStatus: data.syncStatus.present
          ? data.syncStatus.value
          : this.syncStatus,
      deletedForMe: data.deletedForMe.present
          ? data.deletedForMe.value
          : this.deletedForMe,
      deletedForAll: data.deletedForAll.present
          ? data.deletedForAll.value
          : this.deletedForAll,
      isEdited: data.isEdited.present ? data.isEdited.value : this.isEdited,
      editedAt: data.editedAt.present ? data.editedAt.value : this.editedAt,
      attempt: data.attempt.present ? data.attempt.value : this.attempt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LocalMessage(')
          ..write('id: $id, ')
          ..write('serverId: $serverId, ')
          ..write('conversationId: $conversationId, ')
          ..write('localUuid: $localUuid, ')
          ..write('senderId: $senderId, ')
          ..write('messageType: $messageType, ')
          ..write('bodyText: $bodyText, ')
          ..write('replyToServerId: $replyToServerId, ')
          ..write('replyToLocalUuid: $replyToLocalUuid, ')
          ..write('mediaLocalId: $mediaLocalId, ')
          ..write('filePath: $filePath, ')
          ..write('thumbnailPath: $thumbnailPath, ')
          ..write('mimeType: $mimeType, ')
          ..write('fileSize: $fileSize, ')
          ..write('width: $width, ')
          ..write('height: $height, ')
          ..write('duration: $duration, ')
          ..write('serverTimestamp: $serverTimestamp, ')
          ..write('localCreatedAt: $localCreatedAt, ')
          ..write('status: $status, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('deletedForMe: $deletedForMe, ')
          ..write('deletedForAll: $deletedForAll, ')
          ..write('isEdited: $isEdited, ')
          ..write('editedAt: $editedAt, ')
          ..write('attempt: $attempt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hashAll([
    id,
    serverId,
    conversationId,
    localUuid,
    senderId,
    messageType,
    bodyText,
    replyToServerId,
    replyToLocalUuid,
    mediaLocalId,
    filePath,
    thumbnailPath,
    mimeType,
    fileSize,
    width,
    height,
    duration,
    serverTimestamp,
    localCreatedAt,
    status,
    syncStatus,
    deletedForMe,
    deletedForAll,
    isEdited,
    editedAt,
    attempt,
  ]);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalMessage &&
          other.id == this.id &&
          other.serverId == this.serverId &&
          other.conversationId == this.conversationId &&
          other.localUuid == this.localUuid &&
          other.senderId == this.senderId &&
          other.messageType == this.messageType &&
          other.bodyText == this.bodyText &&
          other.replyToServerId == this.replyToServerId &&
          other.replyToLocalUuid == this.replyToLocalUuid &&
          other.mediaLocalId == this.mediaLocalId &&
          other.filePath == this.filePath &&
          other.thumbnailPath == this.thumbnailPath &&
          other.mimeType == this.mimeType &&
          other.fileSize == this.fileSize &&
          other.width == this.width &&
          other.height == this.height &&
          other.duration == this.duration &&
          other.serverTimestamp == this.serverTimestamp &&
          other.localCreatedAt == this.localCreatedAt &&
          other.status == this.status &&
          other.syncStatus == this.syncStatus &&
          other.deletedForMe == this.deletedForMe &&
          other.deletedForAll == this.deletedForAll &&
          other.isEdited == this.isEdited &&
          other.editedAt == this.editedAt &&
          other.attempt == this.attempt);
}

class LocalMessagesCompanion extends UpdateCompanion<LocalMessage> {
  final Value<int> id;
  final Value<int?> serverId;
  final Value<int> conversationId;
  final Value<String> localUuid;
  final Value<int> senderId;
  final Value<String> messageType;
  final Value<String?> bodyText;
  final Value<int?> replyToServerId;
  final Value<int?> replyToLocalUuid;
  final Value<int?> mediaLocalId;
  final Value<String?> filePath;
  final Value<String?> thumbnailPath;
  final Value<String?> mimeType;
  final Value<int?> fileSize;
  final Value<int?> width;
  final Value<int?> height;
  final Value<int?> duration;
  final Value<String> serverTimestamp;
  final Value<String> localCreatedAt;
  final Value<String> status;
  final Value<String> syncStatus;
  final Value<int> deletedForMe;
  final Value<int> deletedForAll;
  final Value<int> isEdited;
  final Value<String?> editedAt;
  final Value<int> attempt;
  const LocalMessagesCompanion({
    this.id = const Value.absent(),
    this.serverId = const Value.absent(),
    this.conversationId = const Value.absent(),
    this.localUuid = const Value.absent(),
    this.senderId = const Value.absent(),
    this.messageType = const Value.absent(),
    this.bodyText = const Value.absent(),
    this.replyToServerId = const Value.absent(),
    this.replyToLocalUuid = const Value.absent(),
    this.mediaLocalId = const Value.absent(),
    this.filePath = const Value.absent(),
    this.thumbnailPath = const Value.absent(),
    this.mimeType = const Value.absent(),
    this.fileSize = const Value.absent(),
    this.width = const Value.absent(),
    this.height = const Value.absent(),
    this.duration = const Value.absent(),
    this.serverTimestamp = const Value.absent(),
    this.localCreatedAt = const Value.absent(),
    this.status = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.deletedForMe = const Value.absent(),
    this.deletedForAll = const Value.absent(),
    this.isEdited = const Value.absent(),
    this.editedAt = const Value.absent(),
    this.attempt = const Value.absent(),
  });
  LocalMessagesCompanion.insert({
    this.id = const Value.absent(),
    this.serverId = const Value.absent(),
    required int conversationId,
    required String localUuid,
    required int senderId,
    this.messageType = const Value.absent(),
    this.bodyText = const Value.absent(),
    this.replyToServerId = const Value.absent(),
    this.replyToLocalUuid = const Value.absent(),
    this.mediaLocalId = const Value.absent(),
    this.filePath = const Value.absent(),
    this.thumbnailPath = const Value.absent(),
    this.mimeType = const Value.absent(),
    this.fileSize = const Value.absent(),
    this.width = const Value.absent(),
    this.height = const Value.absent(),
    this.duration = const Value.absent(),
    this.serverTimestamp = const Value.absent(),
    this.localCreatedAt = const Value.absent(),
    this.status = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.deletedForMe = const Value.absent(),
    this.deletedForAll = const Value.absent(),
    this.isEdited = const Value.absent(),
    this.editedAt = const Value.absent(),
    this.attempt = const Value.absent(),
  }) : conversationId = Value(conversationId),
       localUuid = Value(localUuid),
       senderId = Value(senderId);
  static Insertable<LocalMessage> custom({
    Expression<int>? id,
    Expression<int>? serverId,
    Expression<int>? conversationId,
    Expression<String>? localUuid,
    Expression<int>? senderId,
    Expression<String>? messageType,
    Expression<String>? bodyText,
    Expression<int>? replyToServerId,
    Expression<int>? replyToLocalUuid,
    Expression<int>? mediaLocalId,
    Expression<String>? filePath,
    Expression<String>? thumbnailPath,
    Expression<String>? mimeType,
    Expression<int>? fileSize,
    Expression<int>? width,
    Expression<int>? height,
    Expression<int>? duration,
    Expression<String>? serverTimestamp,
    Expression<String>? localCreatedAt,
    Expression<String>? status,
    Expression<String>? syncStatus,
    Expression<int>? deletedForMe,
    Expression<int>? deletedForAll,
    Expression<int>? isEdited,
    Expression<String>? editedAt,
    Expression<int>? attempt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (serverId != null) 'server_id': serverId,
      if (conversationId != null) 'conversation_id': conversationId,
      if (localUuid != null) 'local_uuid': localUuid,
      if (senderId != null) 'sender_id': senderId,
      if (messageType != null) 'message_type': messageType,
      if (bodyText != null) 'body_text': bodyText,
      if (replyToServerId != null) 'reply_to_server_id': replyToServerId,
      if (replyToLocalUuid != null) 'reply_to_local_uuid': replyToLocalUuid,
      if (mediaLocalId != null) 'media_local_id': mediaLocalId,
      if (filePath != null) 'file_path': filePath,
      if (thumbnailPath != null) 'thumbnail_path': thumbnailPath,
      if (mimeType != null) 'mime_type': mimeType,
      if (fileSize != null) 'file_size': fileSize,
      if (width != null) 'width': width,
      if (height != null) 'height': height,
      if (duration != null) 'duration': duration,
      if (serverTimestamp != null) 'server_timestamp': serverTimestamp,
      if (localCreatedAt != null) 'local_created_at': localCreatedAt,
      if (status != null) 'status': status,
      if (syncStatus != null) 'sync_status': syncStatus,
      if (deletedForMe != null) 'deleted_for_me': deletedForMe,
      if (deletedForAll != null) 'deleted_for_all': deletedForAll,
      if (isEdited != null) 'is_edited': isEdited,
      if (editedAt != null) 'edited_at': editedAt,
      if (attempt != null) 'attempt': attempt,
    });
  }

  LocalMessagesCompanion copyWith({
    Value<int>? id,
    Value<int?>? serverId,
    Value<int>? conversationId,
    Value<String>? localUuid,
    Value<int>? senderId,
    Value<String>? messageType,
    Value<String?>? bodyText,
    Value<int?>? replyToServerId,
    Value<int?>? replyToLocalUuid,
    Value<int?>? mediaLocalId,
    Value<String?>? filePath,
    Value<String?>? thumbnailPath,
    Value<String?>? mimeType,
    Value<int?>? fileSize,
    Value<int?>? width,
    Value<int?>? height,
    Value<int?>? duration,
    Value<String>? serverTimestamp,
    Value<String>? localCreatedAt,
    Value<String>? status,
    Value<String>? syncStatus,
    Value<int>? deletedForMe,
    Value<int>? deletedForAll,
    Value<int>? isEdited,
    Value<String?>? editedAt,
    Value<int>? attempt,
  }) {
    return LocalMessagesCompanion(
      id: id ?? this.id,
      serverId: serverId ?? this.serverId,
      conversationId: conversationId ?? this.conversationId,
      localUuid: localUuid ?? this.localUuid,
      senderId: senderId ?? this.senderId,
      messageType: messageType ?? this.messageType,
      bodyText: bodyText ?? this.bodyText,
      replyToServerId: replyToServerId ?? this.replyToServerId,
      replyToLocalUuid: replyToLocalUuid ?? this.replyToLocalUuid,
      mediaLocalId: mediaLocalId ?? this.mediaLocalId,
      filePath: filePath ?? this.filePath,
      thumbnailPath: thumbnailPath ?? this.thumbnailPath,
      mimeType: mimeType ?? this.mimeType,
      fileSize: fileSize ?? this.fileSize,
      width: width ?? this.width,
      height: height ?? this.height,
      duration: duration ?? this.duration,
      serverTimestamp: serverTimestamp ?? this.serverTimestamp,
      localCreatedAt: localCreatedAt ?? this.localCreatedAt,
      status: status ?? this.status,
      syncStatus: syncStatus ?? this.syncStatus,
      deletedForMe: deletedForMe ?? this.deletedForMe,
      deletedForAll: deletedForAll ?? this.deletedForAll,
      isEdited: isEdited ?? this.isEdited,
      editedAt: editedAt ?? this.editedAt,
      attempt: attempt ?? this.attempt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (serverId.present) {
      map['server_id'] = Variable<int>(serverId.value);
    }
    if (conversationId.present) {
      map['conversation_id'] = Variable<int>(conversationId.value);
    }
    if (localUuid.present) {
      map['local_uuid'] = Variable<String>(localUuid.value);
    }
    if (senderId.present) {
      map['sender_id'] = Variable<int>(senderId.value);
    }
    if (messageType.present) {
      map['message_type'] = Variable<String>(messageType.value);
    }
    if (bodyText.present) {
      map['body_text'] = Variable<String>(bodyText.value);
    }
    if (replyToServerId.present) {
      map['reply_to_server_id'] = Variable<int>(replyToServerId.value);
    }
    if (replyToLocalUuid.present) {
      map['reply_to_local_uuid'] = Variable<int>(replyToLocalUuid.value);
    }
    if (mediaLocalId.present) {
      map['media_local_id'] = Variable<int>(mediaLocalId.value);
    }
    if (filePath.present) {
      map['file_path'] = Variable<String>(filePath.value);
    }
    if (thumbnailPath.present) {
      map['thumbnail_path'] = Variable<String>(thumbnailPath.value);
    }
    if (mimeType.present) {
      map['mime_type'] = Variable<String>(mimeType.value);
    }
    if (fileSize.present) {
      map['file_size'] = Variable<int>(fileSize.value);
    }
    if (width.present) {
      map['width'] = Variable<int>(width.value);
    }
    if (height.present) {
      map['height'] = Variable<int>(height.value);
    }
    if (duration.present) {
      map['duration'] = Variable<int>(duration.value);
    }
    if (serverTimestamp.present) {
      map['server_timestamp'] = Variable<String>(serverTimestamp.value);
    }
    if (localCreatedAt.present) {
      map['local_created_at'] = Variable<String>(localCreatedAt.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (syncStatus.present) {
      map['sync_status'] = Variable<String>(syncStatus.value);
    }
    if (deletedForMe.present) {
      map['deleted_for_me'] = Variable<int>(deletedForMe.value);
    }
    if (deletedForAll.present) {
      map['deleted_for_all'] = Variable<int>(deletedForAll.value);
    }
    if (isEdited.present) {
      map['is_edited'] = Variable<int>(isEdited.value);
    }
    if (editedAt.present) {
      map['edited_at'] = Variable<String>(editedAt.value);
    }
    if (attempt.present) {
      map['attempt'] = Variable<int>(attempt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalMessagesCompanion(')
          ..write('id: $id, ')
          ..write('serverId: $serverId, ')
          ..write('conversationId: $conversationId, ')
          ..write('localUuid: $localUuid, ')
          ..write('senderId: $senderId, ')
          ..write('messageType: $messageType, ')
          ..write('bodyText: $bodyText, ')
          ..write('replyToServerId: $replyToServerId, ')
          ..write('replyToLocalUuid: $replyToLocalUuid, ')
          ..write('mediaLocalId: $mediaLocalId, ')
          ..write('filePath: $filePath, ')
          ..write('thumbnailPath: $thumbnailPath, ')
          ..write('mimeType: $mimeType, ')
          ..write('fileSize: $fileSize, ')
          ..write('width: $width, ')
          ..write('height: $height, ')
          ..write('duration: $duration, ')
          ..write('serverTimestamp: $serverTimestamp, ')
          ..write('localCreatedAt: $localCreatedAt, ')
          ..write('status: $status, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('deletedForMe: $deletedForMe, ')
          ..write('deletedForAll: $deletedForAll, ')
          ..write('isEdited: $isEdited, ')
          ..write('editedAt: $editedAt, ')
          ..write('attempt: $attempt')
          ..write(')'))
        .toString();
  }
}

class $LocalUsersTable extends LocalUsers
    with TableInfo<$LocalUsersTable, LocalUser> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalUsersTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<int> userId = GeneratedColumn<int>(
    'user_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways('UNIQUE'),
  );
  static const VerificationMeta _nameMeta = const VerificationMeta('name');
  @override
  late final GeneratedColumn<String> name = GeneratedColumn<String>(
    'name',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _phoneMeta = const VerificationMeta('phone');
  @override
  late final GeneratedColumn<String> phone = GeneratedColumn<String>(
    'phone',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _emailMeta = const VerificationMeta('email');
  @override
  late final GeneratedColumn<String> email = GeneratedColumn<String>(
    'email',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _usernameMeta = const VerificationMeta(
    'username',
  );
  @override
  late final GeneratedColumn<String> username = GeneratedColumn<String>(
    'username',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _avatarMeta = const VerificationMeta('avatar');
  @override
  late final GeneratedColumn<String> avatar = GeneratedColumn<String>(
    'avatar',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _bioMeta = const VerificationMeta('bio');
  @override
  late final GeneratedColumn<String> bio = GeneratedColumn<String>(
    'bio',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _presenceMeta = const VerificationMeta(
    'presence',
  );
  @override
  late final GeneratedColumn<String> presence = GeneratedColumn<String>(
    'presence',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('offline'),
  );
  static const VerificationMeta _lastSeenMeta = const VerificationMeta(
    'lastSeen',
  );
  @override
  late final GeneratedColumn<String> lastSeen = GeneratedColumn<String>(
    'last_seen',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _isVerifiedMeta = const VerificationMeta(
    'isVerified',
  );
  @override
  late final GeneratedColumn<int> isVerified = GeneratedColumn<int>(
    'is_verified',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<String> updatedAt = GeneratedColumn<String>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  @override
  List<GeneratedColumn> get $columns => [
    userId,
    name,
    phone,
    email,
    username,
    avatar,
    bio,
    presence,
    lastSeen,
    isVerified,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_users';
  @override
  VerificationContext validateIntegrity(
    Insertable<LocalUser> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('user_id')) {
      context.handle(
        _userIdMeta,
        userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta),
      );
    }
    if (data.containsKey('name')) {
      context.handle(
        _nameMeta,
        name.isAcceptableOrUnknown(data['name']!, _nameMeta),
      );
    }
    if (data.containsKey('phone')) {
      context.handle(
        _phoneMeta,
        phone.isAcceptableOrUnknown(data['phone']!, _phoneMeta),
      );
    }
    if (data.containsKey('email')) {
      context.handle(
        _emailMeta,
        email.isAcceptableOrUnknown(data['email']!, _emailMeta),
      );
    }
    if (data.containsKey('username')) {
      context.handle(
        _usernameMeta,
        username.isAcceptableOrUnknown(data['username']!, _usernameMeta),
      );
    }
    if (data.containsKey('avatar')) {
      context.handle(
        _avatarMeta,
        avatar.isAcceptableOrUnknown(data['avatar']!, _avatarMeta),
      );
    }
    if (data.containsKey('bio')) {
      context.handle(
        _bioMeta,
        bio.isAcceptableOrUnknown(data['bio']!, _bioMeta),
      );
    }
    if (data.containsKey('presence')) {
      context.handle(
        _presenceMeta,
        presence.isAcceptableOrUnknown(data['presence']!, _presenceMeta),
      );
    }
    if (data.containsKey('last_seen')) {
      context.handle(
        _lastSeenMeta,
        lastSeen.isAcceptableOrUnknown(data['last_seen']!, _lastSeenMeta),
      );
    }
    if (data.containsKey('is_verified')) {
      context.handle(
        _isVerifiedMeta,
        isVerified.isAcceptableOrUnknown(data['is_verified']!, _isVerifiedMeta),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {userId};
  @override
  LocalUser map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalUser(
      userId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}user_id'],
      )!,
      name: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}name'],
      )!,
      phone: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}phone'],
      )!,
      email: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}email'],
      ),
      username: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}username'],
      ),
      avatar: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}avatar'],
      ),
      bio: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}bio'],
      ),
      presence: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}presence'],
      )!,
      lastSeen: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_seen'],
      ),
      isVerified: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}is_verified'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $LocalUsersTable createAlias(String alias) {
    return $LocalUsersTable(attachedDatabase, alias);
  }
}

class LocalUser extends DataClass implements Insertable<LocalUser> {
  final int userId;
  final String name;
  final String phone;
  final String? email;
  final String? username;
  final String? avatar;
  final String? bio;
  final String presence;
  final String? lastSeen;
  final int isVerified;
  final String updatedAt;
  const LocalUser({
    required this.userId,
    required this.name,
    required this.phone,
    this.email,
    this.username,
    this.avatar,
    this.bio,
    required this.presence,
    this.lastSeen,
    required this.isVerified,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['user_id'] = Variable<int>(userId);
    map['name'] = Variable<String>(name);
    map['phone'] = Variable<String>(phone);
    if (!nullToAbsent || email != null) {
      map['email'] = Variable<String>(email);
    }
    if (!nullToAbsent || username != null) {
      map['username'] = Variable<String>(username);
    }
    if (!nullToAbsent || avatar != null) {
      map['avatar'] = Variable<String>(avatar);
    }
    if (!nullToAbsent || bio != null) {
      map['bio'] = Variable<String>(bio);
    }
    map['presence'] = Variable<String>(presence);
    if (!nullToAbsent || lastSeen != null) {
      map['last_seen'] = Variable<String>(lastSeen);
    }
    map['is_verified'] = Variable<int>(isVerified);
    map['updated_at'] = Variable<String>(updatedAt);
    return map;
  }

  LocalUsersCompanion toCompanion(bool nullToAbsent) {
    return LocalUsersCompanion(
      userId: Value(userId),
      name: Value(name),
      phone: Value(phone),
      email: email == null && nullToAbsent
          ? const Value.absent()
          : Value(email),
      username: username == null && nullToAbsent
          ? const Value.absent()
          : Value(username),
      avatar: avatar == null && nullToAbsent
          ? const Value.absent()
          : Value(avatar),
      bio: bio == null && nullToAbsent ? const Value.absent() : Value(bio),
      presence: Value(presence),
      lastSeen: lastSeen == null && nullToAbsent
          ? const Value.absent()
          : Value(lastSeen),
      isVerified: Value(isVerified),
      updatedAt: Value(updatedAt),
    );
  }

  factory LocalUser.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalUser(
      userId: serializer.fromJson<int>(json['userId']),
      name: serializer.fromJson<String>(json['name']),
      phone: serializer.fromJson<String>(json['phone']),
      email: serializer.fromJson<String?>(json['email']),
      username: serializer.fromJson<String?>(json['username']),
      avatar: serializer.fromJson<String?>(json['avatar']),
      bio: serializer.fromJson<String?>(json['bio']),
      presence: serializer.fromJson<String>(json['presence']),
      lastSeen: serializer.fromJson<String?>(json['lastSeen']),
      isVerified: serializer.fromJson<int>(json['isVerified']),
      updatedAt: serializer.fromJson<String>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'userId': serializer.toJson<int>(userId),
      'name': serializer.toJson<String>(name),
      'phone': serializer.toJson<String>(phone),
      'email': serializer.toJson<String?>(email),
      'username': serializer.toJson<String?>(username),
      'avatar': serializer.toJson<String?>(avatar),
      'bio': serializer.toJson<String?>(bio),
      'presence': serializer.toJson<String>(presence),
      'lastSeen': serializer.toJson<String?>(lastSeen),
      'isVerified': serializer.toJson<int>(isVerified),
      'updatedAt': serializer.toJson<String>(updatedAt),
    };
  }

  LocalUser copyWith({
    int? userId,
    String? name,
    String? phone,
    Value<String?> email = const Value.absent(),
    Value<String?> username = const Value.absent(),
    Value<String?> avatar = const Value.absent(),
    Value<String?> bio = const Value.absent(),
    String? presence,
    Value<String?> lastSeen = const Value.absent(),
    int? isVerified,
    String? updatedAt,
  }) => LocalUser(
    userId: userId ?? this.userId,
    name: name ?? this.name,
    phone: phone ?? this.phone,
    email: email.present ? email.value : this.email,
    username: username.present ? username.value : this.username,
    avatar: avatar.present ? avatar.value : this.avatar,
    bio: bio.present ? bio.value : this.bio,
    presence: presence ?? this.presence,
    lastSeen: lastSeen.present ? lastSeen.value : this.lastSeen,
    isVerified: isVerified ?? this.isVerified,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  LocalUser copyWithCompanion(LocalUsersCompanion data) {
    return LocalUser(
      userId: data.userId.present ? data.userId.value : this.userId,
      name: data.name.present ? data.name.value : this.name,
      phone: data.phone.present ? data.phone.value : this.phone,
      email: data.email.present ? data.email.value : this.email,
      username: data.username.present ? data.username.value : this.username,
      avatar: data.avatar.present ? data.avatar.value : this.avatar,
      bio: data.bio.present ? data.bio.value : this.bio,
      presence: data.presence.present ? data.presence.value : this.presence,
      lastSeen: data.lastSeen.present ? data.lastSeen.value : this.lastSeen,
      isVerified: data.isVerified.present
          ? data.isVerified.value
          : this.isVerified,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LocalUser(')
          ..write('userId: $userId, ')
          ..write('name: $name, ')
          ..write('phone: $phone, ')
          ..write('email: $email, ')
          ..write('username: $username, ')
          ..write('avatar: $avatar, ')
          ..write('bio: $bio, ')
          ..write('presence: $presence, ')
          ..write('lastSeen: $lastSeen, ')
          ..write('isVerified: $isVerified, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    userId,
    name,
    phone,
    email,
    username,
    avatar,
    bio,
    presence,
    lastSeen,
    isVerified,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalUser &&
          other.userId == this.userId &&
          other.name == this.name &&
          other.phone == this.phone &&
          other.email == this.email &&
          other.username == this.username &&
          other.avatar == this.avatar &&
          other.bio == this.bio &&
          other.presence == this.presence &&
          other.lastSeen == this.lastSeen &&
          other.isVerified == this.isVerified &&
          other.updatedAt == this.updatedAt);
}

class LocalUsersCompanion extends UpdateCompanion<LocalUser> {
  final Value<int> userId;
  final Value<String> name;
  final Value<String> phone;
  final Value<String?> email;
  final Value<String?> username;
  final Value<String?> avatar;
  final Value<String?> bio;
  final Value<String> presence;
  final Value<String?> lastSeen;
  final Value<int> isVerified;
  final Value<String> updatedAt;
  const LocalUsersCompanion({
    this.userId = const Value.absent(),
    this.name = const Value.absent(),
    this.phone = const Value.absent(),
    this.email = const Value.absent(),
    this.username = const Value.absent(),
    this.avatar = const Value.absent(),
    this.bio = const Value.absent(),
    this.presence = const Value.absent(),
    this.lastSeen = const Value.absent(),
    this.isVerified = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });
  LocalUsersCompanion.insert({
    this.userId = const Value.absent(),
    this.name = const Value.absent(),
    this.phone = const Value.absent(),
    this.email = const Value.absent(),
    this.username = const Value.absent(),
    this.avatar = const Value.absent(),
    this.bio = const Value.absent(),
    this.presence = const Value.absent(),
    this.lastSeen = const Value.absent(),
    this.isVerified = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });
  static Insertable<LocalUser> custom({
    Expression<int>? userId,
    Expression<String>? name,
    Expression<String>? phone,
    Expression<String>? email,
    Expression<String>? username,
    Expression<String>? avatar,
    Expression<String>? bio,
    Expression<String>? presence,
    Expression<String>? lastSeen,
    Expression<int>? isVerified,
    Expression<String>? updatedAt,
  }) {
    return RawValuesInsertable({
      if (userId != null) 'user_id': userId,
      if (name != null) 'name': name,
      if (phone != null) 'phone': phone,
      if (email != null) 'email': email,
      if (username != null) 'username': username,
      if (avatar != null) 'avatar': avatar,
      if (bio != null) 'bio': bio,
      if (presence != null) 'presence': presence,
      if (lastSeen != null) 'last_seen': lastSeen,
      if (isVerified != null) 'is_verified': isVerified,
      if (updatedAt != null) 'updated_at': updatedAt,
    });
  }

  LocalUsersCompanion copyWith({
    Value<int>? userId,
    Value<String>? name,
    Value<String>? phone,
    Value<String?>? email,
    Value<String?>? username,
    Value<String?>? avatar,
    Value<String?>? bio,
    Value<String>? presence,
    Value<String?>? lastSeen,
    Value<int>? isVerified,
    Value<String>? updatedAt,
  }) {
    return LocalUsersCompanion(
      userId: userId ?? this.userId,
      name: name ?? this.name,
      phone: phone ?? this.phone,
      email: email ?? this.email,
      username: username ?? this.username,
      avatar: avatar ?? this.avatar,
      bio: bio ?? this.bio,
      presence: presence ?? this.presence,
      lastSeen: lastSeen ?? this.lastSeen,
      isVerified: isVerified ?? this.isVerified,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (userId.present) {
      map['user_id'] = Variable<int>(userId.value);
    }
    if (name.present) {
      map['name'] = Variable<String>(name.value);
    }
    if (phone.present) {
      map['phone'] = Variable<String>(phone.value);
    }
    if (email.present) {
      map['email'] = Variable<String>(email.value);
    }
    if (username.present) {
      map['username'] = Variable<String>(username.value);
    }
    if (avatar.present) {
      map['avatar'] = Variable<String>(avatar.value);
    }
    if (bio.present) {
      map['bio'] = Variable<String>(bio.value);
    }
    if (presence.present) {
      map['presence'] = Variable<String>(presence.value);
    }
    if (lastSeen.present) {
      map['last_seen'] = Variable<String>(lastSeen.value);
    }
    if (isVerified.present) {
      map['is_verified'] = Variable<int>(isVerified.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<String>(updatedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalUsersCompanion(')
          ..write('userId: $userId, ')
          ..write('name: $name, ')
          ..write('phone: $phone, ')
          ..write('email: $email, ')
          ..write('username: $username, ')
          ..write('avatar: $avatar, ')
          ..write('bio: $bio, ')
          ..write('presence: $presence, ')
          ..write('lastSeen: $lastSeen, ')
          ..write('isVerified: $isVerified, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }
}

class $LocalMediaTable extends LocalMedia
    with TableInfo<$LocalMediaTable, LocalMediaRecord> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalMediaTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'PRIMARY KEY AUTOINCREMENT',
    ),
  );
  static const VerificationMeta _serverAttachmentIdMeta =
      const VerificationMeta('serverAttachmentId');
  @override
  late final GeneratedColumn<int> serverAttachmentId = GeneratedColumn<int>(
    'server_attachment_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _messageIdMeta = const VerificationMeta(
    'messageId',
  );
  @override
  late final GeneratedColumn<int> messageId = GeneratedColumn<int>(
    'message_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _remoteUrlMeta = const VerificationMeta(
    'remoteUrl',
  );
  @override
  late final GeneratedColumn<String> remoteUrl = GeneratedColumn<String>(
    'remote_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _localPathMeta = const VerificationMeta(
    'localPath',
  );
  @override
  late final GeneratedColumn<String> localPath = GeneratedColumn<String>(
    'local_path',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _mimeTypeMeta = const VerificationMeta(
    'mimeType',
  );
  @override
  late final GeneratedColumn<String> mimeType = GeneratedColumn<String>(
    'mime_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _sizeBytesMeta = const VerificationMeta(
    'sizeBytes',
  );
  @override
  late final GeneratedColumn<int> sizeBytes = GeneratedColumn<int>(
    'size_bytes',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _checksumMeta = const VerificationMeta(
    'checksum',
  );
  @override
  late final GeneratedColumn<String> checksum = GeneratedColumn<String>(
    'checksum',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _categoryMeta = const VerificationMeta(
    'category',
  );
  @override
  late final GeneratedColumn<String> category = GeneratedColumn<String>(
    'category',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('image'),
  );
  static const VerificationMeta _downloadStatusMeta = const VerificationMeta(
    'downloadStatus',
  );
  @override
  late final GeneratedColumn<String> downloadStatus = GeneratedColumn<String>(
    'download_status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('downloaded'),
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<String> createdAt = GeneratedColumn<String>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    serverAttachmentId,
    messageId,
    remoteUrl,
    localPath,
    mimeType,
    sizeBytes,
    checksum,
    category,
    downloadStatus,
    createdAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_media';
  @override
  VerificationContext validateIntegrity(
    Insertable<LocalMediaRecord> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('server_attachment_id')) {
      context.handle(
        _serverAttachmentIdMeta,
        serverAttachmentId.isAcceptableOrUnknown(
          data['server_attachment_id']!,
          _serverAttachmentIdMeta,
        ),
      );
    }
    if (data.containsKey('message_id')) {
      context.handle(
        _messageIdMeta,
        messageId.isAcceptableOrUnknown(data['message_id']!, _messageIdMeta),
      );
    }
    if (data.containsKey('remote_url')) {
      context.handle(
        _remoteUrlMeta,
        remoteUrl.isAcceptableOrUnknown(data['remote_url']!, _remoteUrlMeta),
      );
    }
    if (data.containsKey('local_path')) {
      context.handle(
        _localPathMeta,
        localPath.isAcceptableOrUnknown(data['local_path']!, _localPathMeta),
      );
    } else if (isInserting) {
      context.missing(_localPathMeta);
    }
    if (data.containsKey('mime_type')) {
      context.handle(
        _mimeTypeMeta,
        mimeType.isAcceptableOrUnknown(data['mime_type']!, _mimeTypeMeta),
      );
    }
    if (data.containsKey('size_bytes')) {
      context.handle(
        _sizeBytesMeta,
        sizeBytes.isAcceptableOrUnknown(data['size_bytes']!, _sizeBytesMeta),
      );
    }
    if (data.containsKey('checksum')) {
      context.handle(
        _checksumMeta,
        checksum.isAcceptableOrUnknown(data['checksum']!, _checksumMeta),
      );
    }
    if (data.containsKey('category')) {
      context.handle(
        _categoryMeta,
        category.isAcceptableOrUnknown(data['category']!, _categoryMeta),
      );
    }
    if (data.containsKey('download_status')) {
      context.handle(
        _downloadStatusMeta,
        downloadStatus.isAcceptableOrUnknown(
          data['download_status']!,
          _downloadStatusMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  LocalMediaRecord map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalMediaRecord(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      serverAttachmentId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}server_attachment_id'],
      ),
      messageId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}message_id'],
      ),
      remoteUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}remote_url'],
      ),
      localPath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_path'],
      )!,
      mimeType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}mime_type'],
      )!,
      sizeBytes: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}size_bytes'],
      )!,
      checksum: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}checksum'],
      )!,
      category: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}category'],
      )!,
      downloadStatus: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}download_status'],
      )!,
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}created_at'],
      )!,
    );
  }

  @override
  $LocalMediaTable createAlias(String alias) {
    return $LocalMediaTable(attachedDatabase, alias);
  }
}

class LocalMediaRecord extends DataClass
    implements Insertable<LocalMediaRecord> {
  final int id;
  final int? serverAttachmentId;
  final int? messageId;
  final String? remoteUrl;
  final String localPath;
  final String mimeType;
  final int sizeBytes;
  final String checksum;
  final String category;
  final String downloadStatus;
  final String createdAt;
  const LocalMediaRecord({
    required this.id,
    this.serverAttachmentId,
    this.messageId,
    this.remoteUrl,
    required this.localPath,
    required this.mimeType,
    required this.sizeBytes,
    required this.checksum,
    required this.category,
    required this.downloadStatus,
    required this.createdAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    if (!nullToAbsent || serverAttachmentId != null) {
      map['server_attachment_id'] = Variable<int>(serverAttachmentId);
    }
    if (!nullToAbsent || messageId != null) {
      map['message_id'] = Variable<int>(messageId);
    }
    if (!nullToAbsent || remoteUrl != null) {
      map['remote_url'] = Variable<String>(remoteUrl);
    }
    map['local_path'] = Variable<String>(localPath);
    map['mime_type'] = Variable<String>(mimeType);
    map['size_bytes'] = Variable<int>(sizeBytes);
    map['checksum'] = Variable<String>(checksum);
    map['category'] = Variable<String>(category);
    map['download_status'] = Variable<String>(downloadStatus);
    map['created_at'] = Variable<String>(createdAt);
    return map;
  }

  LocalMediaCompanion toCompanion(bool nullToAbsent) {
    return LocalMediaCompanion(
      id: Value(id),
      serverAttachmentId: serverAttachmentId == null && nullToAbsent
          ? const Value.absent()
          : Value(serverAttachmentId),
      messageId: messageId == null && nullToAbsent
          ? const Value.absent()
          : Value(messageId),
      remoteUrl: remoteUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(remoteUrl),
      localPath: Value(localPath),
      mimeType: Value(mimeType),
      sizeBytes: Value(sizeBytes),
      checksum: Value(checksum),
      category: Value(category),
      downloadStatus: Value(downloadStatus),
      createdAt: Value(createdAt),
    );
  }

  factory LocalMediaRecord.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalMediaRecord(
      id: serializer.fromJson<int>(json['id']),
      serverAttachmentId: serializer.fromJson<int?>(json['serverAttachmentId']),
      messageId: serializer.fromJson<int?>(json['messageId']),
      remoteUrl: serializer.fromJson<String?>(json['remoteUrl']),
      localPath: serializer.fromJson<String>(json['localPath']),
      mimeType: serializer.fromJson<String>(json['mimeType']),
      sizeBytes: serializer.fromJson<int>(json['sizeBytes']),
      checksum: serializer.fromJson<String>(json['checksum']),
      category: serializer.fromJson<String>(json['category']),
      downloadStatus: serializer.fromJson<String>(json['downloadStatus']),
      createdAt: serializer.fromJson<String>(json['createdAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'serverAttachmentId': serializer.toJson<int?>(serverAttachmentId),
      'messageId': serializer.toJson<int?>(messageId),
      'remoteUrl': serializer.toJson<String?>(remoteUrl),
      'localPath': serializer.toJson<String>(localPath),
      'mimeType': serializer.toJson<String>(mimeType),
      'sizeBytes': serializer.toJson<int>(sizeBytes),
      'checksum': serializer.toJson<String>(checksum),
      'category': serializer.toJson<String>(category),
      'downloadStatus': serializer.toJson<String>(downloadStatus),
      'createdAt': serializer.toJson<String>(createdAt),
    };
  }

  LocalMediaRecord copyWith({
    int? id,
    Value<int?> serverAttachmentId = const Value.absent(),
    Value<int?> messageId = const Value.absent(),
    Value<String?> remoteUrl = const Value.absent(),
    String? localPath,
    String? mimeType,
    int? sizeBytes,
    String? checksum,
    String? category,
    String? downloadStatus,
    String? createdAt,
  }) => LocalMediaRecord(
    id: id ?? this.id,
    serverAttachmentId: serverAttachmentId.present
        ? serverAttachmentId.value
        : this.serverAttachmentId,
    messageId: messageId.present ? messageId.value : this.messageId,
    remoteUrl: remoteUrl.present ? remoteUrl.value : this.remoteUrl,
    localPath: localPath ?? this.localPath,
    mimeType: mimeType ?? this.mimeType,
    sizeBytes: sizeBytes ?? this.sizeBytes,
    checksum: checksum ?? this.checksum,
    category: category ?? this.category,
    downloadStatus: downloadStatus ?? this.downloadStatus,
    createdAt: createdAt ?? this.createdAt,
  );
  LocalMediaRecord copyWithCompanion(LocalMediaCompanion data) {
    return LocalMediaRecord(
      id: data.id.present ? data.id.value : this.id,
      serverAttachmentId: data.serverAttachmentId.present
          ? data.serverAttachmentId.value
          : this.serverAttachmentId,
      messageId: data.messageId.present ? data.messageId.value : this.messageId,
      remoteUrl: data.remoteUrl.present ? data.remoteUrl.value : this.remoteUrl,
      localPath: data.localPath.present ? data.localPath.value : this.localPath,
      mimeType: data.mimeType.present ? data.mimeType.value : this.mimeType,
      sizeBytes: data.sizeBytes.present ? data.sizeBytes.value : this.sizeBytes,
      checksum: data.checksum.present ? data.checksum.value : this.checksum,
      category: data.category.present ? data.category.value : this.category,
      downloadStatus: data.downloadStatus.present
          ? data.downloadStatus.value
          : this.downloadStatus,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LocalMediaRecord(')
          ..write('id: $id, ')
          ..write('serverAttachmentId: $serverAttachmentId, ')
          ..write('messageId: $messageId, ')
          ..write('remoteUrl: $remoteUrl, ')
          ..write('localPath: $localPath, ')
          ..write('mimeType: $mimeType, ')
          ..write('sizeBytes: $sizeBytes, ')
          ..write('checksum: $checksum, ')
          ..write('category: $category, ')
          ..write('downloadStatus: $downloadStatus, ')
          ..write('createdAt: $createdAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    serverAttachmentId,
    messageId,
    remoteUrl,
    localPath,
    mimeType,
    sizeBytes,
    checksum,
    category,
    downloadStatus,
    createdAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalMediaRecord &&
          other.id == this.id &&
          other.serverAttachmentId == this.serverAttachmentId &&
          other.messageId == this.messageId &&
          other.remoteUrl == this.remoteUrl &&
          other.localPath == this.localPath &&
          other.mimeType == this.mimeType &&
          other.sizeBytes == this.sizeBytes &&
          other.checksum == this.checksum &&
          other.category == this.category &&
          other.downloadStatus == this.downloadStatus &&
          other.createdAt == this.createdAt);
}

class LocalMediaCompanion extends UpdateCompanion<LocalMediaRecord> {
  final Value<int> id;
  final Value<int?> serverAttachmentId;
  final Value<int?> messageId;
  final Value<String?> remoteUrl;
  final Value<String> localPath;
  final Value<String> mimeType;
  final Value<int> sizeBytes;
  final Value<String> checksum;
  final Value<String> category;
  final Value<String> downloadStatus;
  final Value<String> createdAt;
  const LocalMediaCompanion({
    this.id = const Value.absent(),
    this.serverAttachmentId = const Value.absent(),
    this.messageId = const Value.absent(),
    this.remoteUrl = const Value.absent(),
    this.localPath = const Value.absent(),
    this.mimeType = const Value.absent(),
    this.sizeBytes = const Value.absent(),
    this.checksum = const Value.absent(),
    this.category = const Value.absent(),
    this.downloadStatus = const Value.absent(),
    this.createdAt = const Value.absent(),
  });
  LocalMediaCompanion.insert({
    this.id = const Value.absent(),
    this.serverAttachmentId = const Value.absent(),
    this.messageId = const Value.absent(),
    this.remoteUrl = const Value.absent(),
    required String localPath,
    this.mimeType = const Value.absent(),
    this.sizeBytes = const Value.absent(),
    this.checksum = const Value.absent(),
    this.category = const Value.absent(),
    this.downloadStatus = const Value.absent(),
    this.createdAt = const Value.absent(),
  }) : localPath = Value(localPath);
  static Insertable<LocalMediaRecord> custom({
    Expression<int>? id,
    Expression<int>? serverAttachmentId,
    Expression<int>? messageId,
    Expression<String>? remoteUrl,
    Expression<String>? localPath,
    Expression<String>? mimeType,
    Expression<int>? sizeBytes,
    Expression<String>? checksum,
    Expression<String>? category,
    Expression<String>? downloadStatus,
    Expression<String>? createdAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (serverAttachmentId != null)
        'server_attachment_id': serverAttachmentId,
      if (messageId != null) 'message_id': messageId,
      if (remoteUrl != null) 'remote_url': remoteUrl,
      if (localPath != null) 'local_path': localPath,
      if (mimeType != null) 'mime_type': mimeType,
      if (sizeBytes != null) 'size_bytes': sizeBytes,
      if (checksum != null) 'checksum': checksum,
      if (category != null) 'category': category,
      if (downloadStatus != null) 'download_status': downloadStatus,
      if (createdAt != null) 'created_at': createdAt,
    });
  }

  LocalMediaCompanion copyWith({
    Value<int>? id,
    Value<int?>? serverAttachmentId,
    Value<int?>? messageId,
    Value<String?>? remoteUrl,
    Value<String>? localPath,
    Value<String>? mimeType,
    Value<int>? sizeBytes,
    Value<String>? checksum,
    Value<String>? category,
    Value<String>? downloadStatus,
    Value<String>? createdAt,
  }) {
    return LocalMediaCompanion(
      id: id ?? this.id,
      serverAttachmentId: serverAttachmentId ?? this.serverAttachmentId,
      messageId: messageId ?? this.messageId,
      remoteUrl: remoteUrl ?? this.remoteUrl,
      localPath: localPath ?? this.localPath,
      mimeType: mimeType ?? this.mimeType,
      sizeBytes: sizeBytes ?? this.sizeBytes,
      checksum: checksum ?? this.checksum,
      category: category ?? this.category,
      downloadStatus: downloadStatus ?? this.downloadStatus,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (serverAttachmentId.present) {
      map['server_attachment_id'] = Variable<int>(serverAttachmentId.value);
    }
    if (messageId.present) {
      map['message_id'] = Variable<int>(messageId.value);
    }
    if (remoteUrl.present) {
      map['remote_url'] = Variable<String>(remoteUrl.value);
    }
    if (localPath.present) {
      map['local_path'] = Variable<String>(localPath.value);
    }
    if (mimeType.present) {
      map['mime_type'] = Variable<String>(mimeType.value);
    }
    if (sizeBytes.present) {
      map['size_bytes'] = Variable<int>(sizeBytes.value);
    }
    if (checksum.present) {
      map['checksum'] = Variable<String>(checksum.value);
    }
    if (category.present) {
      map['category'] = Variable<String>(category.value);
    }
    if (downloadStatus.present) {
      map['download_status'] = Variable<String>(downloadStatus.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<String>(createdAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalMediaCompanion(')
          ..write('id: $id, ')
          ..write('serverAttachmentId: $serverAttachmentId, ')
          ..write('messageId: $messageId, ')
          ..write('remoteUrl: $remoteUrl, ')
          ..write('localPath: $localPath, ')
          ..write('mimeType: $mimeType, ')
          ..write('sizeBytes: $sizeBytes, ')
          ..write('checksum: $checksum, ')
          ..write('category: $category, ')
          ..write('downloadStatus: $downloadStatus, ')
          ..write('createdAt: $createdAt')
          ..write(')'))
        .toString();
  }
}

class $LocalOutboxTable extends LocalOutbox
    with TableInfo<$LocalOutboxTable, OutboxItem> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalOutboxTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'PRIMARY KEY AUTOINCREMENT',
    ),
  );
  static const VerificationMeta _operationMeta = const VerificationMeta(
    'operation',
  );
  @override
  late final GeneratedColumn<String> operation = GeneratedColumn<String>(
    'operation',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _entityTypeMeta = const VerificationMeta(
    'entityType',
  );
  @override
  late final GeneratedColumn<String> entityType = GeneratedColumn<String>(
    'entity_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('message'),
  );
  static const VerificationMeta _entityRefMeta = const VerificationMeta(
    'entityRef',
  );
  @override
  late final GeneratedColumn<String> entityRef = GeneratedColumn<String>(
    'entity_ref',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _payloadMeta = const VerificationMeta(
    'payload',
  );
  @override
  late final GeneratedColumn<String> payload = GeneratedColumn<String>(
    'payload',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('{}'),
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );
  static const VerificationMeta _retryCountMeta = const VerificationMeta(
    'retryCount',
  );
  @override
  late final GeneratedColumn<int> retryCount = GeneratedColumn<int>(
    'retry_count',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _nextRetryAtMeta = const VerificationMeta(
    'nextRetryAt',
  );
  @override
  late final GeneratedColumn<String> nextRetryAt = GeneratedColumn<String>(
    'next_retry_at',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _lastErrorMeta = const VerificationMeta(
    'lastError',
  );
  @override
  late final GeneratedColumn<String> lastError = GeneratedColumn<String>(
    'last_error',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<String> createdAt = GeneratedColumn<String>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _lastAttemptAtMeta = const VerificationMeta(
    'lastAttemptAt',
  );
  @override
  late final GeneratedColumn<String> lastAttemptAt = GeneratedColumn<String>(
    'last_attempt_at',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    operation,
    entityType,
    entityRef,
    payload,
    status,
    retryCount,
    nextRetryAt,
    lastError,
    createdAt,
    lastAttemptAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_outbox';
  @override
  VerificationContext validateIntegrity(
    Insertable<OutboxItem> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('operation')) {
      context.handle(
        _operationMeta,
        operation.isAcceptableOrUnknown(data['operation']!, _operationMeta),
      );
    } else if (isInserting) {
      context.missing(_operationMeta);
    }
    if (data.containsKey('entity_type')) {
      context.handle(
        _entityTypeMeta,
        entityType.isAcceptableOrUnknown(data['entity_type']!, _entityTypeMeta),
      );
    }
    if (data.containsKey('entity_ref')) {
      context.handle(
        _entityRefMeta,
        entityRef.isAcceptableOrUnknown(data['entity_ref']!, _entityRefMeta),
      );
    }
    if (data.containsKey('payload')) {
      context.handle(
        _payloadMeta,
        payload.isAcceptableOrUnknown(data['payload']!, _payloadMeta),
      );
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('retry_count')) {
      context.handle(
        _retryCountMeta,
        retryCount.isAcceptableOrUnknown(data['retry_count']!, _retryCountMeta),
      );
    }
    if (data.containsKey('next_retry_at')) {
      context.handle(
        _nextRetryAtMeta,
        nextRetryAt.isAcceptableOrUnknown(
          data['next_retry_at']!,
          _nextRetryAtMeta,
        ),
      );
    }
    if (data.containsKey('last_error')) {
      context.handle(
        _lastErrorMeta,
        lastError.isAcceptableOrUnknown(data['last_error']!, _lastErrorMeta),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    }
    if (data.containsKey('last_attempt_at')) {
      context.handle(
        _lastAttemptAtMeta,
        lastAttemptAt.isAcceptableOrUnknown(
          data['last_attempt_at']!,
          _lastAttemptAtMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  OutboxItem map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return OutboxItem(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      operation: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}operation'],
      )!,
      entityType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}entity_type'],
      )!,
      entityRef: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}entity_ref'],
      )!,
      payload: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      retryCount: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}retry_count'],
      )!,
      nextRetryAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}next_retry_at'],
      ),
      lastError: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_error'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}created_at'],
      )!,
      lastAttemptAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_attempt_at'],
      ),
    );
  }

  @override
  $LocalOutboxTable createAlias(String alias) {
    return $LocalOutboxTable(attachedDatabase, alias);
  }
}

class OutboxItem extends DataClass implements Insertable<OutboxItem> {
  final int id;
  final String operation;
  final String entityType;
  final String entityRef;
  final String payload;
  final String status;
  final int retryCount;
  final String? nextRetryAt;
  final String? lastError;
  final String createdAt;
  final String? lastAttemptAt;
  const OutboxItem({
    required this.id,
    required this.operation,
    required this.entityType,
    required this.entityRef,
    required this.payload,
    required this.status,
    required this.retryCount,
    this.nextRetryAt,
    this.lastError,
    required this.createdAt,
    this.lastAttemptAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['operation'] = Variable<String>(operation);
    map['entity_type'] = Variable<String>(entityType);
    map['entity_ref'] = Variable<String>(entityRef);
    map['payload'] = Variable<String>(payload);
    map['status'] = Variable<String>(status);
    map['retry_count'] = Variable<int>(retryCount);
    if (!nullToAbsent || nextRetryAt != null) {
      map['next_retry_at'] = Variable<String>(nextRetryAt);
    }
    if (!nullToAbsent || lastError != null) {
      map['last_error'] = Variable<String>(lastError);
    }
    map['created_at'] = Variable<String>(createdAt);
    if (!nullToAbsent || lastAttemptAt != null) {
      map['last_attempt_at'] = Variable<String>(lastAttemptAt);
    }
    return map;
  }

  LocalOutboxCompanion toCompanion(bool nullToAbsent) {
    return LocalOutboxCompanion(
      id: Value(id),
      operation: Value(operation),
      entityType: Value(entityType),
      entityRef: Value(entityRef),
      payload: Value(payload),
      status: Value(status),
      retryCount: Value(retryCount),
      nextRetryAt: nextRetryAt == null && nullToAbsent
          ? const Value.absent()
          : Value(nextRetryAt),
      lastError: lastError == null && nullToAbsent
          ? const Value.absent()
          : Value(lastError),
      createdAt: Value(createdAt),
      lastAttemptAt: lastAttemptAt == null && nullToAbsent
          ? const Value.absent()
          : Value(lastAttemptAt),
    );
  }

  factory OutboxItem.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return OutboxItem(
      id: serializer.fromJson<int>(json['id']),
      operation: serializer.fromJson<String>(json['operation']),
      entityType: serializer.fromJson<String>(json['entityType']),
      entityRef: serializer.fromJson<String>(json['entityRef']),
      payload: serializer.fromJson<String>(json['payload']),
      status: serializer.fromJson<String>(json['status']),
      retryCount: serializer.fromJson<int>(json['retryCount']),
      nextRetryAt: serializer.fromJson<String?>(json['nextRetryAt']),
      lastError: serializer.fromJson<String?>(json['lastError']),
      createdAt: serializer.fromJson<String>(json['createdAt']),
      lastAttemptAt: serializer.fromJson<String?>(json['lastAttemptAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'operation': serializer.toJson<String>(operation),
      'entityType': serializer.toJson<String>(entityType),
      'entityRef': serializer.toJson<String>(entityRef),
      'payload': serializer.toJson<String>(payload),
      'status': serializer.toJson<String>(status),
      'retryCount': serializer.toJson<int>(retryCount),
      'nextRetryAt': serializer.toJson<String?>(nextRetryAt),
      'lastError': serializer.toJson<String?>(lastError),
      'createdAt': serializer.toJson<String>(createdAt),
      'lastAttemptAt': serializer.toJson<String?>(lastAttemptAt),
    };
  }

  OutboxItem copyWith({
    int? id,
    String? operation,
    String? entityType,
    String? entityRef,
    String? payload,
    String? status,
    int? retryCount,
    Value<String?> nextRetryAt = const Value.absent(),
    Value<String?> lastError = const Value.absent(),
    String? createdAt,
    Value<String?> lastAttemptAt = const Value.absent(),
  }) => OutboxItem(
    id: id ?? this.id,
    operation: operation ?? this.operation,
    entityType: entityType ?? this.entityType,
    entityRef: entityRef ?? this.entityRef,
    payload: payload ?? this.payload,
    status: status ?? this.status,
    retryCount: retryCount ?? this.retryCount,
    nextRetryAt: nextRetryAt.present ? nextRetryAt.value : this.nextRetryAt,
    lastError: lastError.present ? lastError.value : this.lastError,
    createdAt: createdAt ?? this.createdAt,
    lastAttemptAt: lastAttemptAt.present
        ? lastAttemptAt.value
        : this.lastAttemptAt,
  );
  OutboxItem copyWithCompanion(LocalOutboxCompanion data) {
    return OutboxItem(
      id: data.id.present ? data.id.value : this.id,
      operation: data.operation.present ? data.operation.value : this.operation,
      entityType: data.entityType.present
          ? data.entityType.value
          : this.entityType,
      entityRef: data.entityRef.present ? data.entityRef.value : this.entityRef,
      payload: data.payload.present ? data.payload.value : this.payload,
      status: data.status.present ? data.status.value : this.status,
      retryCount: data.retryCount.present
          ? data.retryCount.value
          : this.retryCount,
      nextRetryAt: data.nextRetryAt.present
          ? data.nextRetryAt.value
          : this.nextRetryAt,
      lastError: data.lastError.present ? data.lastError.value : this.lastError,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      lastAttemptAt: data.lastAttemptAt.present
          ? data.lastAttemptAt.value
          : this.lastAttemptAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('OutboxItem(')
          ..write('id: $id, ')
          ..write('operation: $operation, ')
          ..write('entityType: $entityType, ')
          ..write('entityRef: $entityRef, ')
          ..write('payload: $payload, ')
          ..write('status: $status, ')
          ..write('retryCount: $retryCount, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('lastError: $lastError, ')
          ..write('createdAt: $createdAt, ')
          ..write('lastAttemptAt: $lastAttemptAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    operation,
    entityType,
    entityRef,
    payload,
    status,
    retryCount,
    nextRetryAt,
    lastError,
    createdAt,
    lastAttemptAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutboxItem &&
          other.id == this.id &&
          other.operation == this.operation &&
          other.entityType == this.entityType &&
          other.entityRef == this.entityRef &&
          other.payload == this.payload &&
          other.status == this.status &&
          other.retryCount == this.retryCount &&
          other.nextRetryAt == this.nextRetryAt &&
          other.lastError == this.lastError &&
          other.createdAt == this.createdAt &&
          other.lastAttemptAt == this.lastAttemptAt);
}

class LocalOutboxCompanion extends UpdateCompanion<OutboxItem> {
  final Value<int> id;
  final Value<String> operation;
  final Value<String> entityType;
  final Value<String> entityRef;
  final Value<String> payload;
  final Value<String> status;
  final Value<int> retryCount;
  final Value<String?> nextRetryAt;
  final Value<String?> lastError;
  final Value<String> createdAt;
  final Value<String?> lastAttemptAt;
  const LocalOutboxCompanion({
    this.id = const Value.absent(),
    this.operation = const Value.absent(),
    this.entityType = const Value.absent(),
    this.entityRef = const Value.absent(),
    this.payload = const Value.absent(),
    this.status = const Value.absent(),
    this.retryCount = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    this.lastError = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.lastAttemptAt = const Value.absent(),
  });
  LocalOutboxCompanion.insert({
    this.id = const Value.absent(),
    required String operation,
    this.entityType = const Value.absent(),
    this.entityRef = const Value.absent(),
    this.payload = const Value.absent(),
    this.status = const Value.absent(),
    this.retryCount = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    this.lastError = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.lastAttemptAt = const Value.absent(),
  }) : operation = Value(operation);
  static Insertable<OutboxItem> custom({
    Expression<int>? id,
    Expression<String>? operation,
    Expression<String>? entityType,
    Expression<String>? entityRef,
    Expression<String>? payload,
    Expression<String>? status,
    Expression<int>? retryCount,
    Expression<String>? nextRetryAt,
    Expression<String>? lastError,
    Expression<String>? createdAt,
    Expression<String>? lastAttemptAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (operation != null) 'operation': operation,
      if (entityType != null) 'entity_type': entityType,
      if (entityRef != null) 'entity_ref': entityRef,
      if (payload != null) 'payload': payload,
      if (status != null) 'status': status,
      if (retryCount != null) 'retry_count': retryCount,
      if (nextRetryAt != null) 'next_retry_at': nextRetryAt,
      if (lastError != null) 'last_error': lastError,
      if (createdAt != null) 'created_at': createdAt,
      if (lastAttemptAt != null) 'last_attempt_at': lastAttemptAt,
    });
  }

  LocalOutboxCompanion copyWith({
    Value<int>? id,
    Value<String>? operation,
    Value<String>? entityType,
    Value<String>? entityRef,
    Value<String>? payload,
    Value<String>? status,
    Value<int>? retryCount,
    Value<String?>? nextRetryAt,
    Value<String?>? lastError,
    Value<String>? createdAt,
    Value<String?>? lastAttemptAt,
  }) {
    return LocalOutboxCompanion(
      id: id ?? this.id,
      operation: operation ?? this.operation,
      entityType: entityType ?? this.entityType,
      entityRef: entityRef ?? this.entityRef,
      payload: payload ?? this.payload,
      status: status ?? this.status,
      retryCount: retryCount ?? this.retryCount,
      nextRetryAt: nextRetryAt ?? this.nextRetryAt,
      lastError: lastError ?? this.lastError,
      createdAt: createdAt ?? this.createdAt,
      lastAttemptAt: lastAttemptAt ?? this.lastAttemptAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (operation.present) {
      map['operation'] = Variable<String>(operation.value);
    }
    if (entityType.present) {
      map['entity_type'] = Variable<String>(entityType.value);
    }
    if (entityRef.present) {
      map['entity_ref'] = Variable<String>(entityRef.value);
    }
    if (payload.present) {
      map['payload'] = Variable<String>(payload.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (retryCount.present) {
      map['retry_count'] = Variable<int>(retryCount.value);
    }
    if (nextRetryAt.present) {
      map['next_retry_at'] = Variable<String>(nextRetryAt.value);
    }
    if (lastError.present) {
      map['last_error'] = Variable<String>(lastError.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<String>(createdAt.value);
    }
    if (lastAttemptAt.present) {
      map['last_attempt_at'] = Variable<String>(lastAttemptAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalOutboxCompanion(')
          ..write('id: $id, ')
          ..write('operation: $operation, ')
          ..write('entityType: $entityType, ')
          ..write('entityRef: $entityRef, ')
          ..write('payload: $payload, ')
          ..write('status: $status, ')
          ..write('retryCount: $retryCount, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('lastError: $lastError, ')
          ..write('createdAt: $createdAt, ')
          ..write('lastAttemptAt: $lastAttemptAt')
          ..write(')'))
        .toString();
  }
}

class $LocalSyncStateTable extends LocalSyncState
    with TableInfo<$LocalSyncStateTable, SyncState> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalSyncStateTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'PRIMARY KEY AUTOINCREMENT',
    ),
  );
  static const VerificationMeta _stateKeyMeta = const VerificationMeta(
    'stateKey',
  );
  @override
  late final GeneratedColumn<String> stateKey = GeneratedColumn<String>(
    'state_key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
    defaultConstraints: GeneratedColumn.constraintIsAlways('UNIQUE'),
  );
  static const VerificationMeta _stateValueMeta = const VerificationMeta(
    'stateValue',
  );
  @override
  late final GeneratedColumn<String> stateValue = GeneratedColumn<String>(
    'state_value',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<String> updatedAt = GeneratedColumn<String>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant(''),
  );
  @override
  List<GeneratedColumn> get $columns => [id, stateKey, stateValue, updatedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_sync_state';
  @override
  VerificationContext validateIntegrity(
    Insertable<SyncState> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('state_key')) {
      context.handle(
        _stateKeyMeta,
        stateKey.isAcceptableOrUnknown(data['state_key']!, _stateKeyMeta),
      );
    } else if (isInserting) {
      context.missing(_stateKeyMeta);
    }
    if (data.containsKey('state_value')) {
      context.handle(
        _stateValueMeta,
        stateValue.isAcceptableOrUnknown(data['state_value']!, _stateValueMeta),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  SyncState map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return SyncState(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      stateKey: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}state_key'],
      )!,
      stateValue: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}state_value'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $LocalSyncStateTable createAlias(String alias) {
    return $LocalSyncStateTable(attachedDatabase, alias);
  }
}

class SyncState extends DataClass implements Insertable<SyncState> {
  final int id;
  final String stateKey;
  final String stateValue;
  final String updatedAt;
  const SyncState({
    required this.id,
    required this.stateKey,
    required this.stateValue,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['state_key'] = Variable<String>(stateKey);
    map['state_value'] = Variable<String>(stateValue);
    map['updated_at'] = Variable<String>(updatedAt);
    return map;
  }

  LocalSyncStateCompanion toCompanion(bool nullToAbsent) {
    return LocalSyncStateCompanion(
      id: Value(id),
      stateKey: Value(stateKey),
      stateValue: Value(stateValue),
      updatedAt: Value(updatedAt),
    );
  }

  factory SyncState.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return SyncState(
      id: serializer.fromJson<int>(json['id']),
      stateKey: serializer.fromJson<String>(json['stateKey']),
      stateValue: serializer.fromJson<String>(json['stateValue']),
      updatedAt: serializer.fromJson<String>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'stateKey': serializer.toJson<String>(stateKey),
      'stateValue': serializer.toJson<String>(stateValue),
      'updatedAt': serializer.toJson<String>(updatedAt),
    };
  }

  SyncState copyWith({
    int? id,
    String? stateKey,
    String? stateValue,
    String? updatedAt,
  }) => SyncState(
    id: id ?? this.id,
    stateKey: stateKey ?? this.stateKey,
    stateValue: stateValue ?? this.stateValue,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  SyncState copyWithCompanion(LocalSyncStateCompanion data) {
    return SyncState(
      id: data.id.present ? data.id.value : this.id,
      stateKey: data.stateKey.present ? data.stateKey.value : this.stateKey,
      stateValue: data.stateValue.present
          ? data.stateValue.value
          : this.stateValue,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('SyncState(')
          ..write('id: $id, ')
          ..write('stateKey: $stateKey, ')
          ..write('stateValue: $stateValue, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, stateKey, stateValue, updatedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is SyncState &&
          other.id == this.id &&
          other.stateKey == this.stateKey &&
          other.stateValue == this.stateValue &&
          other.updatedAt == this.updatedAt);
}

class LocalSyncStateCompanion extends UpdateCompanion<SyncState> {
  final Value<int> id;
  final Value<String> stateKey;
  final Value<String> stateValue;
  final Value<String> updatedAt;
  const LocalSyncStateCompanion({
    this.id = const Value.absent(),
    this.stateKey = const Value.absent(),
    this.stateValue = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });
  LocalSyncStateCompanion.insert({
    this.id = const Value.absent(),
    required String stateKey,
    this.stateValue = const Value.absent(),
    this.updatedAt = const Value.absent(),
  }) : stateKey = Value(stateKey);
  static Insertable<SyncState> custom({
    Expression<int>? id,
    Expression<String>? stateKey,
    Expression<String>? stateValue,
    Expression<String>? updatedAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (stateKey != null) 'state_key': stateKey,
      if (stateValue != null) 'state_value': stateValue,
      if (updatedAt != null) 'updated_at': updatedAt,
    });
  }

  LocalSyncStateCompanion copyWith({
    Value<int>? id,
    Value<String>? stateKey,
    Value<String>? stateValue,
    Value<String>? updatedAt,
  }) {
    return LocalSyncStateCompanion(
      id: id ?? this.id,
      stateKey: stateKey ?? this.stateKey,
      stateValue: stateValue ?? this.stateValue,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (stateKey.present) {
      map['state_key'] = Variable<String>(stateKey.value);
    }
    if (stateValue.present) {
      map['state_value'] = Variable<String>(stateValue.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<String>(updatedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalSyncStateCompanion(')
          ..write('id: $id, ')
          ..write('stateKey: $stateKey, ')
          ..write('stateValue: $stateValue, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }
}

abstract class _$LocalNovaDb extends GeneratedDatabase {
  _$LocalNovaDb(QueryExecutor e) : super(e);
  $LocalNovaDbManager get managers => $LocalNovaDbManager(this);
  late final $LocalChatsTable localChats = $LocalChatsTable(this);
  late final $LocalMessagesTable localMessages = $LocalMessagesTable(this);
  late final $LocalUsersTable localUsers = $LocalUsersTable(this);
  late final $LocalMediaTable localMedia = $LocalMediaTable(this);
  late final $LocalOutboxTable localOutbox = $LocalOutboxTable(this);
  late final $LocalSyncStateTable localSyncState = $LocalSyncStateTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    localChats,
    localMessages,
    localUsers,
    localMedia,
    localOutbox,
    localSyncState,
  ];
}

typedef $$LocalChatsTableCreateCompanionBuilder =
    LocalChatsCompanion Function({
      Value<int> id,
      required String chatId,
      Value<String> convType,
      Value<String> title,
      Value<String?> avatar,
      Value<int> lastMessageId,
      Value<String> lastMessagePreview,
      Value<String?> lastMessageAt,
      Value<int> unreadCount,
      Value<bool> muted,
      Value<bool> archived,
      Value<bool> pinned,
      Value<bool> isGroup,
      Value<int> memberCount,
      Value<int> otherUserId,
      Value<int?> groupId,
      Value<String> updatedAt,
      Value<int> deletedForMe,
    });
typedef $$LocalChatsTableUpdateCompanionBuilder =
    LocalChatsCompanion Function({
      Value<int> id,
      Value<String> chatId,
      Value<String> convType,
      Value<String> title,
      Value<String?> avatar,
      Value<int> lastMessageId,
      Value<String> lastMessagePreview,
      Value<String?> lastMessageAt,
      Value<int> unreadCount,
      Value<bool> muted,
      Value<bool> archived,
      Value<bool> pinned,
      Value<bool> isGroup,
      Value<int> memberCount,
      Value<int> otherUserId,
      Value<int?> groupId,
      Value<String> updatedAt,
      Value<int> deletedForMe,
    });

class $$LocalChatsTableFilterComposer
    extends Composer<_$LocalNovaDb, $LocalChatsTable> {
  $$LocalChatsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get chatId => $composableBuilder(
    column: $table.chatId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get convType => $composableBuilder(
    column: $table.convType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get title => $composableBuilder(
    column: $table.title,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get avatar => $composableBuilder(
    column: $table.avatar,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get lastMessageId => $composableBuilder(
    column: $table.lastMessageId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastMessagePreview => $composableBuilder(
    column: $table.lastMessagePreview,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastMessageAt => $composableBuilder(
    column: $table.lastMessageAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get unreadCount => $composableBuilder(
    column: $table.unreadCount,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get muted => $composableBuilder(
    column: $table.muted,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get archived => $composableBuilder(
    column: $table.archived,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get pinned => $composableBuilder(
    column: $table.pinned,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get isGroup => $composableBuilder(
    column: $table.isGroup,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get memberCount => $composableBuilder(
    column: $table.memberCount,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get otherUserId => $composableBuilder(
    column: $table.otherUserId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get groupId => $composableBuilder(
    column: $table.groupId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get deletedForMe => $composableBuilder(
    column: $table.deletedForMe,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalChatsTableOrderingComposer
    extends Composer<_$LocalNovaDb, $LocalChatsTable> {
  $$LocalChatsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get chatId => $composableBuilder(
    column: $table.chatId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get convType => $composableBuilder(
    column: $table.convType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get title => $composableBuilder(
    column: $table.title,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get avatar => $composableBuilder(
    column: $table.avatar,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get lastMessageId => $composableBuilder(
    column: $table.lastMessageId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastMessagePreview => $composableBuilder(
    column: $table.lastMessagePreview,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastMessageAt => $composableBuilder(
    column: $table.lastMessageAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get unreadCount => $composableBuilder(
    column: $table.unreadCount,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get muted => $composableBuilder(
    column: $table.muted,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get archived => $composableBuilder(
    column: $table.archived,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get pinned => $composableBuilder(
    column: $table.pinned,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get isGroup => $composableBuilder(
    column: $table.isGroup,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get memberCount => $composableBuilder(
    column: $table.memberCount,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get otherUserId => $composableBuilder(
    column: $table.otherUserId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get groupId => $composableBuilder(
    column: $table.groupId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get deletedForMe => $composableBuilder(
    column: $table.deletedForMe,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalChatsTableAnnotationComposer
    extends Composer<_$LocalNovaDb, $LocalChatsTable> {
  $$LocalChatsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get chatId =>
      $composableBuilder(column: $table.chatId, builder: (column) => column);

  GeneratedColumn<String> get convType =>
      $composableBuilder(column: $table.convType, builder: (column) => column);

  GeneratedColumn<String> get title =>
      $composableBuilder(column: $table.title, builder: (column) => column);

  GeneratedColumn<String> get avatar =>
      $composableBuilder(column: $table.avatar, builder: (column) => column);

  GeneratedColumn<int> get lastMessageId => $composableBuilder(
    column: $table.lastMessageId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get lastMessagePreview => $composableBuilder(
    column: $table.lastMessagePreview,
    builder: (column) => column,
  );

  GeneratedColumn<String> get lastMessageAt => $composableBuilder(
    column: $table.lastMessageAt,
    builder: (column) => column,
  );

  GeneratedColumn<int> get unreadCount => $composableBuilder(
    column: $table.unreadCount,
    builder: (column) => column,
  );

  GeneratedColumn<bool> get muted =>
      $composableBuilder(column: $table.muted, builder: (column) => column);

  GeneratedColumn<bool> get archived =>
      $composableBuilder(column: $table.archived, builder: (column) => column);

  GeneratedColumn<bool> get pinned =>
      $composableBuilder(column: $table.pinned, builder: (column) => column);

  GeneratedColumn<bool> get isGroup =>
      $composableBuilder(column: $table.isGroup, builder: (column) => column);

  GeneratedColumn<int> get memberCount => $composableBuilder(
    column: $table.memberCount,
    builder: (column) => column,
  );

  GeneratedColumn<int> get otherUserId => $composableBuilder(
    column: $table.otherUserId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get groupId =>
      $composableBuilder(column: $table.groupId, builder: (column) => column);

  GeneratedColumn<String> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);

  GeneratedColumn<int> get deletedForMe => $composableBuilder(
    column: $table.deletedForMe,
    builder: (column) => column,
  );
}

class $$LocalChatsTableTableManager
    extends
        RootTableManager<
          _$LocalNovaDb,
          $LocalChatsTable,
          LocalChat,
          $$LocalChatsTableFilterComposer,
          $$LocalChatsTableOrderingComposer,
          $$LocalChatsTableAnnotationComposer,
          $$LocalChatsTableCreateCompanionBuilder,
          $$LocalChatsTableUpdateCompanionBuilder,
          (
            LocalChat,
            BaseReferences<_$LocalNovaDb, $LocalChatsTable, LocalChat>,
          ),
          LocalChat,
          PrefetchHooks Function()
        > {
  $$LocalChatsTableTableManager(_$LocalNovaDb db, $LocalChatsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalChatsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalChatsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalChatsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> chatId = const Value.absent(),
                Value<String> convType = const Value.absent(),
                Value<String> title = const Value.absent(),
                Value<String?> avatar = const Value.absent(),
                Value<int> lastMessageId = const Value.absent(),
                Value<String> lastMessagePreview = const Value.absent(),
                Value<String?> lastMessageAt = const Value.absent(),
                Value<int> unreadCount = const Value.absent(),
                Value<bool> muted = const Value.absent(),
                Value<bool> archived = const Value.absent(),
                Value<bool> pinned = const Value.absent(),
                Value<bool> isGroup = const Value.absent(),
                Value<int> memberCount = const Value.absent(),
                Value<int> otherUserId = const Value.absent(),
                Value<int?> groupId = const Value.absent(),
                Value<String> updatedAt = const Value.absent(),
                Value<int> deletedForMe = const Value.absent(),
              }) => LocalChatsCompanion(
                id: id,
                chatId: chatId,
                convType: convType,
                title: title,
                avatar: avatar,
                lastMessageId: lastMessageId,
                lastMessagePreview: lastMessagePreview,
                lastMessageAt: lastMessageAt,
                unreadCount: unreadCount,
                muted: muted,
                archived: archived,
                pinned: pinned,
                isGroup: isGroup,
                memberCount: memberCount,
                otherUserId: otherUserId,
                groupId: groupId,
                updatedAt: updatedAt,
                deletedForMe: deletedForMe,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String chatId,
                Value<String> convType = const Value.absent(),
                Value<String> title = const Value.absent(),
                Value<String?> avatar = const Value.absent(),
                Value<int> lastMessageId = const Value.absent(),
                Value<String> lastMessagePreview = const Value.absent(),
                Value<String?> lastMessageAt = const Value.absent(),
                Value<int> unreadCount = const Value.absent(),
                Value<bool> muted = const Value.absent(),
                Value<bool> archived = const Value.absent(),
                Value<bool> pinned = const Value.absent(),
                Value<bool> isGroup = const Value.absent(),
                Value<int> memberCount = const Value.absent(),
                Value<int> otherUserId = const Value.absent(),
                Value<int?> groupId = const Value.absent(),
                Value<String> updatedAt = const Value.absent(),
                Value<int> deletedForMe = const Value.absent(),
              }) => LocalChatsCompanion.insert(
                id: id,
                chatId: chatId,
                convType: convType,
                title: title,
                avatar: avatar,
                lastMessageId: lastMessageId,
                lastMessagePreview: lastMessagePreview,
                lastMessageAt: lastMessageAt,
                unreadCount: unreadCount,
                muted: muted,
                archived: archived,
                pinned: pinned,
                isGroup: isGroup,
                memberCount: memberCount,
                otherUserId: otherUserId,
                groupId: groupId,
                updatedAt: updatedAt,
                deletedForMe: deletedForMe,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalChatsTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalNovaDb,
      $LocalChatsTable,
      LocalChat,
      $$LocalChatsTableFilterComposer,
      $$LocalChatsTableOrderingComposer,
      $$LocalChatsTableAnnotationComposer,
      $$LocalChatsTableCreateCompanionBuilder,
      $$LocalChatsTableUpdateCompanionBuilder,
      (LocalChat, BaseReferences<_$LocalNovaDb, $LocalChatsTable, LocalChat>),
      LocalChat,
      PrefetchHooks Function()
    >;
typedef $$LocalMessagesTableCreateCompanionBuilder =
    LocalMessagesCompanion Function({
      Value<int> id,
      Value<int?> serverId,
      required int conversationId,
      required String localUuid,
      required int senderId,
      Value<String> messageType,
      Value<String?> bodyText,
      Value<int?> replyToServerId,
      Value<int?> replyToLocalUuid,
      Value<int?> mediaLocalId,
      Value<String?> filePath,
      Value<String?> thumbnailPath,
      Value<String?> mimeType,
      Value<int?> fileSize,
      Value<int?> width,
      Value<int?> height,
      Value<int?> duration,
      Value<String> serverTimestamp,
      Value<String> localCreatedAt,
      Value<String> status,
      Value<String> syncStatus,
      Value<int> deletedForMe,
      Value<int> deletedForAll,
      Value<int> isEdited,
      Value<String?> editedAt,
      Value<int> attempt,
    });
typedef $$LocalMessagesTableUpdateCompanionBuilder =
    LocalMessagesCompanion Function({
      Value<int> id,
      Value<int?> serverId,
      Value<int> conversationId,
      Value<String> localUuid,
      Value<int> senderId,
      Value<String> messageType,
      Value<String?> bodyText,
      Value<int?> replyToServerId,
      Value<int?> replyToLocalUuid,
      Value<int?> mediaLocalId,
      Value<String?> filePath,
      Value<String?> thumbnailPath,
      Value<String?> mimeType,
      Value<int?> fileSize,
      Value<int?> width,
      Value<int?> height,
      Value<int?> duration,
      Value<String> serverTimestamp,
      Value<String> localCreatedAt,
      Value<String> status,
      Value<String> syncStatus,
      Value<int> deletedForMe,
      Value<int> deletedForAll,
      Value<int> isEdited,
      Value<String?> editedAt,
      Value<int> attempt,
    });

class $$LocalMessagesTableFilterComposer
    extends Composer<_$LocalNovaDb, $LocalMessagesTable> {
  $$LocalMessagesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get serverId => $composableBuilder(
    column: $table.serverId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get conversationId => $composableBuilder(
    column: $table.conversationId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get localUuid => $composableBuilder(
    column: $table.localUuid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get senderId => $composableBuilder(
    column: $table.senderId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get messageType => $composableBuilder(
    column: $table.messageType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get bodyText => $composableBuilder(
    column: $table.bodyText,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get replyToServerId => $composableBuilder(
    column: $table.replyToServerId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get replyToLocalUuid => $composableBuilder(
    column: $table.replyToLocalUuid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get mediaLocalId => $composableBuilder(
    column: $table.mediaLocalId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get filePath => $composableBuilder(
    column: $table.filePath,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get thumbnailPath => $composableBuilder(
    column: $table.thumbnailPath,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get mimeType => $composableBuilder(
    column: $table.mimeType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get fileSize => $composableBuilder(
    column: $table.fileSize,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get width => $composableBuilder(
    column: $table.width,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get height => $composableBuilder(
    column: $table.height,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get duration => $composableBuilder(
    column: $table.duration,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get serverTimestamp => $composableBuilder(
    column: $table.serverTimestamp,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get localCreatedAt => $composableBuilder(
    column: $table.localCreatedAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get syncStatus => $composableBuilder(
    column: $table.syncStatus,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get deletedForMe => $composableBuilder(
    column: $table.deletedForMe,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get deletedForAll => $composableBuilder(
    column: $table.deletedForAll,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get isEdited => $composableBuilder(
    column: $table.isEdited,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get editedAt => $composableBuilder(
    column: $table.editedAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get attempt => $composableBuilder(
    column: $table.attempt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalMessagesTableOrderingComposer
    extends Composer<_$LocalNovaDb, $LocalMessagesTable> {
  $$LocalMessagesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get serverId => $composableBuilder(
    column: $table.serverId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get conversationId => $composableBuilder(
    column: $table.conversationId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localUuid => $composableBuilder(
    column: $table.localUuid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get senderId => $composableBuilder(
    column: $table.senderId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get messageType => $composableBuilder(
    column: $table.messageType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get bodyText => $composableBuilder(
    column: $table.bodyText,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get replyToServerId => $composableBuilder(
    column: $table.replyToServerId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get replyToLocalUuid => $composableBuilder(
    column: $table.replyToLocalUuid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get mediaLocalId => $composableBuilder(
    column: $table.mediaLocalId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get filePath => $composableBuilder(
    column: $table.filePath,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get thumbnailPath => $composableBuilder(
    column: $table.thumbnailPath,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get mimeType => $composableBuilder(
    column: $table.mimeType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get fileSize => $composableBuilder(
    column: $table.fileSize,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get width => $composableBuilder(
    column: $table.width,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get height => $composableBuilder(
    column: $table.height,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get duration => $composableBuilder(
    column: $table.duration,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get serverTimestamp => $composableBuilder(
    column: $table.serverTimestamp,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localCreatedAt => $composableBuilder(
    column: $table.localCreatedAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get syncStatus => $composableBuilder(
    column: $table.syncStatus,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get deletedForMe => $composableBuilder(
    column: $table.deletedForMe,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get deletedForAll => $composableBuilder(
    column: $table.deletedForAll,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get isEdited => $composableBuilder(
    column: $table.isEdited,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get editedAt => $composableBuilder(
    column: $table.editedAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attempt => $composableBuilder(
    column: $table.attempt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalMessagesTableAnnotationComposer
    extends Composer<_$LocalNovaDb, $LocalMessagesTable> {
  $$LocalMessagesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<int> get serverId =>
      $composableBuilder(column: $table.serverId, builder: (column) => column);

  GeneratedColumn<int> get conversationId => $composableBuilder(
    column: $table.conversationId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get localUuid =>
      $composableBuilder(column: $table.localUuid, builder: (column) => column);

  GeneratedColumn<int> get senderId =>
      $composableBuilder(column: $table.senderId, builder: (column) => column);

  GeneratedColumn<String> get messageType => $composableBuilder(
    column: $table.messageType,
    builder: (column) => column,
  );

  GeneratedColumn<String> get bodyText =>
      $composableBuilder(column: $table.bodyText, builder: (column) => column);

  GeneratedColumn<int> get replyToServerId => $composableBuilder(
    column: $table.replyToServerId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get replyToLocalUuid => $composableBuilder(
    column: $table.replyToLocalUuid,
    builder: (column) => column,
  );

  GeneratedColumn<int> get mediaLocalId => $composableBuilder(
    column: $table.mediaLocalId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get filePath =>
      $composableBuilder(column: $table.filePath, builder: (column) => column);

  GeneratedColumn<String> get thumbnailPath => $composableBuilder(
    column: $table.thumbnailPath,
    builder: (column) => column,
  );

  GeneratedColumn<String> get mimeType =>
      $composableBuilder(column: $table.mimeType, builder: (column) => column);

  GeneratedColumn<int> get fileSize =>
      $composableBuilder(column: $table.fileSize, builder: (column) => column);

  GeneratedColumn<int> get width =>
      $composableBuilder(column: $table.width, builder: (column) => column);

  GeneratedColumn<int> get height =>
      $composableBuilder(column: $table.height, builder: (column) => column);

  GeneratedColumn<int> get duration =>
      $composableBuilder(column: $table.duration, builder: (column) => column);

  GeneratedColumn<String> get serverTimestamp => $composableBuilder(
    column: $table.serverTimestamp,
    builder: (column) => column,
  );

  GeneratedColumn<String> get localCreatedAt => $composableBuilder(
    column: $table.localCreatedAt,
    builder: (column) => column,
  );

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get syncStatus => $composableBuilder(
    column: $table.syncStatus,
    builder: (column) => column,
  );

  GeneratedColumn<int> get deletedForMe => $composableBuilder(
    column: $table.deletedForMe,
    builder: (column) => column,
  );

  GeneratedColumn<int> get deletedForAll => $composableBuilder(
    column: $table.deletedForAll,
    builder: (column) => column,
  );

  GeneratedColumn<int> get isEdited =>
      $composableBuilder(column: $table.isEdited, builder: (column) => column);

  GeneratedColumn<String> get editedAt =>
      $composableBuilder(column: $table.editedAt, builder: (column) => column);

  GeneratedColumn<int> get attempt =>
      $composableBuilder(column: $table.attempt, builder: (column) => column);
}

class $$LocalMessagesTableTableManager
    extends
        RootTableManager<
          _$LocalNovaDb,
          $LocalMessagesTable,
          LocalMessage,
          $$LocalMessagesTableFilterComposer,
          $$LocalMessagesTableOrderingComposer,
          $$LocalMessagesTableAnnotationComposer,
          $$LocalMessagesTableCreateCompanionBuilder,
          $$LocalMessagesTableUpdateCompanionBuilder,
          (
            LocalMessage,
            BaseReferences<_$LocalNovaDb, $LocalMessagesTable, LocalMessage>,
          ),
          LocalMessage,
          PrefetchHooks Function()
        > {
  $$LocalMessagesTableTableManager(_$LocalNovaDb db, $LocalMessagesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalMessagesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalMessagesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalMessagesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<int?> serverId = const Value.absent(),
                Value<int> conversationId = const Value.absent(),
                Value<String> localUuid = const Value.absent(),
                Value<int> senderId = const Value.absent(),
                Value<String> messageType = const Value.absent(),
                Value<String?> bodyText = const Value.absent(),
                Value<int?> replyToServerId = const Value.absent(),
                Value<int?> replyToLocalUuid = const Value.absent(),
                Value<int?> mediaLocalId = const Value.absent(),
                Value<String?> filePath = const Value.absent(),
                Value<String?> thumbnailPath = const Value.absent(),
                Value<String?> mimeType = const Value.absent(),
                Value<int?> fileSize = const Value.absent(),
                Value<int?> width = const Value.absent(),
                Value<int?> height = const Value.absent(),
                Value<int?> duration = const Value.absent(),
                Value<String> serverTimestamp = const Value.absent(),
                Value<String> localCreatedAt = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<String> syncStatus = const Value.absent(),
                Value<int> deletedForMe = const Value.absent(),
                Value<int> deletedForAll = const Value.absent(),
                Value<int> isEdited = const Value.absent(),
                Value<String?> editedAt = const Value.absent(),
                Value<int> attempt = const Value.absent(),
              }) => LocalMessagesCompanion(
                id: id,
                serverId: serverId,
                conversationId: conversationId,
                localUuid: localUuid,
                senderId: senderId,
                messageType: messageType,
                bodyText: bodyText,
                replyToServerId: replyToServerId,
                replyToLocalUuid: replyToLocalUuid,
                mediaLocalId: mediaLocalId,
                filePath: filePath,
                thumbnailPath: thumbnailPath,
                mimeType: mimeType,
                fileSize: fileSize,
                width: width,
                height: height,
                duration: duration,
                serverTimestamp: serverTimestamp,
                localCreatedAt: localCreatedAt,
                status: status,
                syncStatus: syncStatus,
                deletedForMe: deletedForMe,
                deletedForAll: deletedForAll,
                isEdited: isEdited,
                editedAt: editedAt,
                attempt: attempt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<int?> serverId = const Value.absent(),
                required int conversationId,
                required String localUuid,
                required int senderId,
                Value<String> messageType = const Value.absent(),
                Value<String?> bodyText = const Value.absent(),
                Value<int?> replyToServerId = const Value.absent(),
                Value<int?> replyToLocalUuid = const Value.absent(),
                Value<int?> mediaLocalId = const Value.absent(),
                Value<String?> filePath = const Value.absent(),
                Value<String?> thumbnailPath = const Value.absent(),
                Value<String?> mimeType = const Value.absent(),
                Value<int?> fileSize = const Value.absent(),
                Value<int?> width = const Value.absent(),
                Value<int?> height = const Value.absent(),
                Value<int?> duration = const Value.absent(),
                Value<String> serverTimestamp = const Value.absent(),
                Value<String> localCreatedAt = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<String> syncStatus = const Value.absent(),
                Value<int> deletedForMe = const Value.absent(),
                Value<int> deletedForAll = const Value.absent(),
                Value<int> isEdited = const Value.absent(),
                Value<String?> editedAt = const Value.absent(),
                Value<int> attempt = const Value.absent(),
              }) => LocalMessagesCompanion.insert(
                id: id,
                serverId: serverId,
                conversationId: conversationId,
                localUuid: localUuid,
                senderId: senderId,
                messageType: messageType,
                bodyText: bodyText,
                replyToServerId: replyToServerId,
                replyToLocalUuid: replyToLocalUuid,
                mediaLocalId: mediaLocalId,
                filePath: filePath,
                thumbnailPath: thumbnailPath,
                mimeType: mimeType,
                fileSize: fileSize,
                width: width,
                height: height,
                duration: duration,
                serverTimestamp: serverTimestamp,
                localCreatedAt: localCreatedAt,
                status: status,
                syncStatus: syncStatus,
                deletedForMe: deletedForMe,
                deletedForAll: deletedForAll,
                isEdited: isEdited,
                editedAt: editedAt,
                attempt: attempt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalMessagesTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalNovaDb,
      $LocalMessagesTable,
      LocalMessage,
      $$LocalMessagesTableFilterComposer,
      $$LocalMessagesTableOrderingComposer,
      $$LocalMessagesTableAnnotationComposer,
      $$LocalMessagesTableCreateCompanionBuilder,
      $$LocalMessagesTableUpdateCompanionBuilder,
      (
        LocalMessage,
        BaseReferences<_$LocalNovaDb, $LocalMessagesTable, LocalMessage>,
      ),
      LocalMessage,
      PrefetchHooks Function()
    >;
typedef $$LocalUsersTableCreateCompanionBuilder =
    LocalUsersCompanion Function({
      Value<int> userId,
      Value<String> name,
      Value<String> phone,
      Value<String?> email,
      Value<String?> username,
      Value<String?> avatar,
      Value<String?> bio,
      Value<String> presence,
      Value<String?> lastSeen,
      Value<int> isVerified,
      Value<String> updatedAt,
    });
typedef $$LocalUsersTableUpdateCompanionBuilder =
    LocalUsersCompanion Function({
      Value<int> userId,
      Value<String> name,
      Value<String> phone,
      Value<String?> email,
      Value<String?> username,
      Value<String?> avatar,
      Value<String?> bio,
      Value<String> presence,
      Value<String?> lastSeen,
      Value<int> isVerified,
      Value<String> updatedAt,
    });

class $$LocalUsersTableFilterComposer
    extends Composer<_$LocalNovaDb, $LocalUsersTable> {
  $$LocalUsersTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get name => $composableBuilder(
    column: $table.name,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get phone => $composableBuilder(
    column: $table.phone,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get email => $composableBuilder(
    column: $table.email,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get username => $composableBuilder(
    column: $table.username,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get avatar => $composableBuilder(
    column: $table.avatar,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get bio => $composableBuilder(
    column: $table.bio,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get presence => $composableBuilder(
    column: $table.presence,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastSeen => $composableBuilder(
    column: $table.lastSeen,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get isVerified => $composableBuilder(
    column: $table.isVerified,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalUsersTableOrderingComposer
    extends Composer<_$LocalNovaDb, $LocalUsersTable> {
  $$LocalUsersTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get name => $composableBuilder(
    column: $table.name,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get phone => $composableBuilder(
    column: $table.phone,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get email => $composableBuilder(
    column: $table.email,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get username => $composableBuilder(
    column: $table.username,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get avatar => $composableBuilder(
    column: $table.avatar,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get bio => $composableBuilder(
    column: $table.bio,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get presence => $composableBuilder(
    column: $table.presence,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastSeen => $composableBuilder(
    column: $table.lastSeen,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get isVerified => $composableBuilder(
    column: $table.isVerified,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalUsersTableAnnotationComposer
    extends Composer<_$LocalNovaDb, $LocalUsersTable> {
  $$LocalUsersTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get name =>
      $composableBuilder(column: $table.name, builder: (column) => column);

  GeneratedColumn<String> get phone =>
      $composableBuilder(column: $table.phone, builder: (column) => column);

  GeneratedColumn<String> get email =>
      $composableBuilder(column: $table.email, builder: (column) => column);

  GeneratedColumn<String> get username =>
      $composableBuilder(column: $table.username, builder: (column) => column);

  GeneratedColumn<String> get avatar =>
      $composableBuilder(column: $table.avatar, builder: (column) => column);

  GeneratedColumn<String> get bio =>
      $composableBuilder(column: $table.bio, builder: (column) => column);

  GeneratedColumn<String> get presence =>
      $composableBuilder(column: $table.presence, builder: (column) => column);

  GeneratedColumn<String> get lastSeen =>
      $composableBuilder(column: $table.lastSeen, builder: (column) => column);

  GeneratedColumn<int> get isVerified => $composableBuilder(
    column: $table.isVerified,
    builder: (column) => column,
  );

  GeneratedColumn<String> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$LocalUsersTableTableManager
    extends
        RootTableManager<
          _$LocalNovaDb,
          $LocalUsersTable,
          LocalUser,
          $$LocalUsersTableFilterComposer,
          $$LocalUsersTableOrderingComposer,
          $$LocalUsersTableAnnotationComposer,
          $$LocalUsersTableCreateCompanionBuilder,
          $$LocalUsersTableUpdateCompanionBuilder,
          (
            LocalUser,
            BaseReferences<_$LocalNovaDb, $LocalUsersTable, LocalUser>,
          ),
          LocalUser,
          PrefetchHooks Function()
        > {
  $$LocalUsersTableTableManager(_$LocalNovaDb db, $LocalUsersTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalUsersTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalUsersTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalUsersTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> userId = const Value.absent(),
                Value<String> name = const Value.absent(),
                Value<String> phone = const Value.absent(),
                Value<String?> email = const Value.absent(),
                Value<String?> username = const Value.absent(),
                Value<String?> avatar = const Value.absent(),
                Value<String?> bio = const Value.absent(),
                Value<String> presence = const Value.absent(),
                Value<String?> lastSeen = const Value.absent(),
                Value<int> isVerified = const Value.absent(),
                Value<String> updatedAt = const Value.absent(),
              }) => LocalUsersCompanion(
                userId: userId,
                name: name,
                phone: phone,
                email: email,
                username: username,
                avatar: avatar,
                bio: bio,
                presence: presence,
                lastSeen: lastSeen,
                isVerified: isVerified,
                updatedAt: updatedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> userId = const Value.absent(),
                Value<String> name = const Value.absent(),
                Value<String> phone = const Value.absent(),
                Value<String?> email = const Value.absent(),
                Value<String?> username = const Value.absent(),
                Value<String?> avatar = const Value.absent(),
                Value<String?> bio = const Value.absent(),
                Value<String> presence = const Value.absent(),
                Value<String?> lastSeen = const Value.absent(),
                Value<int> isVerified = const Value.absent(),
                Value<String> updatedAt = const Value.absent(),
              }) => LocalUsersCompanion.insert(
                userId: userId,
                name: name,
                phone: phone,
                email: email,
                username: username,
                avatar: avatar,
                bio: bio,
                presence: presence,
                lastSeen: lastSeen,
                isVerified: isVerified,
                updatedAt: updatedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalUsersTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalNovaDb,
      $LocalUsersTable,
      LocalUser,
      $$LocalUsersTableFilterComposer,
      $$LocalUsersTableOrderingComposer,
      $$LocalUsersTableAnnotationComposer,
      $$LocalUsersTableCreateCompanionBuilder,
      $$LocalUsersTableUpdateCompanionBuilder,
      (LocalUser, BaseReferences<_$LocalNovaDb, $LocalUsersTable, LocalUser>),
      LocalUser,
      PrefetchHooks Function()
    >;
typedef $$LocalMediaTableCreateCompanionBuilder =
    LocalMediaCompanion Function({
      Value<int> id,
      Value<int?> serverAttachmentId,
      Value<int?> messageId,
      Value<String?> remoteUrl,
      required String localPath,
      Value<String> mimeType,
      Value<int> sizeBytes,
      Value<String> checksum,
      Value<String> category,
      Value<String> downloadStatus,
      Value<String> createdAt,
    });
typedef $$LocalMediaTableUpdateCompanionBuilder =
    LocalMediaCompanion Function({
      Value<int> id,
      Value<int?> serverAttachmentId,
      Value<int?> messageId,
      Value<String?> remoteUrl,
      Value<String> localPath,
      Value<String> mimeType,
      Value<int> sizeBytes,
      Value<String> checksum,
      Value<String> category,
      Value<String> downloadStatus,
      Value<String> createdAt,
    });

class $$LocalMediaTableFilterComposer
    extends Composer<_$LocalNovaDb, $LocalMediaTable> {
  $$LocalMediaTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get serverAttachmentId => $composableBuilder(
    column: $table.serverAttachmentId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get messageId => $composableBuilder(
    column: $table.messageId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get remoteUrl => $composableBuilder(
    column: $table.remoteUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get localPath => $composableBuilder(
    column: $table.localPath,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get mimeType => $composableBuilder(
    column: $table.mimeType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get sizeBytes => $composableBuilder(
    column: $table.sizeBytes,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get checksum => $composableBuilder(
    column: $table.checksum,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get category => $composableBuilder(
    column: $table.category,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get downloadStatus => $composableBuilder(
    column: $table.downloadStatus,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalMediaTableOrderingComposer
    extends Composer<_$LocalNovaDb, $LocalMediaTable> {
  $$LocalMediaTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get serverAttachmentId => $composableBuilder(
    column: $table.serverAttachmentId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get messageId => $composableBuilder(
    column: $table.messageId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get remoteUrl => $composableBuilder(
    column: $table.remoteUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localPath => $composableBuilder(
    column: $table.localPath,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get mimeType => $composableBuilder(
    column: $table.mimeType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get sizeBytes => $composableBuilder(
    column: $table.sizeBytes,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get checksum => $composableBuilder(
    column: $table.checksum,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get category => $composableBuilder(
    column: $table.category,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get downloadStatus => $composableBuilder(
    column: $table.downloadStatus,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalMediaTableAnnotationComposer
    extends Composer<_$LocalNovaDb, $LocalMediaTable> {
  $$LocalMediaTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<int> get serverAttachmentId => $composableBuilder(
    column: $table.serverAttachmentId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get messageId =>
      $composableBuilder(column: $table.messageId, builder: (column) => column);

  GeneratedColumn<String> get remoteUrl =>
      $composableBuilder(column: $table.remoteUrl, builder: (column) => column);

  GeneratedColumn<String> get localPath =>
      $composableBuilder(column: $table.localPath, builder: (column) => column);

  GeneratedColumn<String> get mimeType =>
      $composableBuilder(column: $table.mimeType, builder: (column) => column);

  GeneratedColumn<int> get sizeBytes =>
      $composableBuilder(column: $table.sizeBytes, builder: (column) => column);

  GeneratedColumn<String> get checksum =>
      $composableBuilder(column: $table.checksum, builder: (column) => column);

  GeneratedColumn<String> get category =>
      $composableBuilder(column: $table.category, builder: (column) => column);

  GeneratedColumn<String> get downloadStatus => $composableBuilder(
    column: $table.downloadStatus,
    builder: (column) => column,
  );

  GeneratedColumn<String> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);
}

class $$LocalMediaTableTableManager
    extends
        RootTableManager<
          _$LocalNovaDb,
          $LocalMediaTable,
          LocalMediaRecord,
          $$LocalMediaTableFilterComposer,
          $$LocalMediaTableOrderingComposer,
          $$LocalMediaTableAnnotationComposer,
          $$LocalMediaTableCreateCompanionBuilder,
          $$LocalMediaTableUpdateCompanionBuilder,
          (
            LocalMediaRecord,
            BaseReferences<_$LocalNovaDb, $LocalMediaTable, LocalMediaRecord>,
          ),
          LocalMediaRecord,
          PrefetchHooks Function()
        > {
  $$LocalMediaTableTableManager(_$LocalNovaDb db, $LocalMediaTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalMediaTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalMediaTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalMediaTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<int?> serverAttachmentId = const Value.absent(),
                Value<int?> messageId = const Value.absent(),
                Value<String?> remoteUrl = const Value.absent(),
                Value<String> localPath = const Value.absent(),
                Value<String> mimeType = const Value.absent(),
                Value<int> sizeBytes = const Value.absent(),
                Value<String> checksum = const Value.absent(),
                Value<String> category = const Value.absent(),
                Value<String> downloadStatus = const Value.absent(),
                Value<String> createdAt = const Value.absent(),
              }) => LocalMediaCompanion(
                id: id,
                serverAttachmentId: serverAttachmentId,
                messageId: messageId,
                remoteUrl: remoteUrl,
                localPath: localPath,
                mimeType: mimeType,
                sizeBytes: sizeBytes,
                checksum: checksum,
                category: category,
                downloadStatus: downloadStatus,
                createdAt: createdAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<int?> serverAttachmentId = const Value.absent(),
                Value<int?> messageId = const Value.absent(),
                Value<String?> remoteUrl = const Value.absent(),
                required String localPath,
                Value<String> mimeType = const Value.absent(),
                Value<int> sizeBytes = const Value.absent(),
                Value<String> checksum = const Value.absent(),
                Value<String> category = const Value.absent(),
                Value<String> downloadStatus = const Value.absent(),
                Value<String> createdAt = const Value.absent(),
              }) => LocalMediaCompanion.insert(
                id: id,
                serverAttachmentId: serverAttachmentId,
                messageId: messageId,
                remoteUrl: remoteUrl,
                localPath: localPath,
                mimeType: mimeType,
                sizeBytes: sizeBytes,
                checksum: checksum,
                category: category,
                downloadStatus: downloadStatus,
                createdAt: createdAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalMediaTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalNovaDb,
      $LocalMediaTable,
      LocalMediaRecord,
      $$LocalMediaTableFilterComposer,
      $$LocalMediaTableOrderingComposer,
      $$LocalMediaTableAnnotationComposer,
      $$LocalMediaTableCreateCompanionBuilder,
      $$LocalMediaTableUpdateCompanionBuilder,
      (
        LocalMediaRecord,
        BaseReferences<_$LocalNovaDb, $LocalMediaTable, LocalMediaRecord>,
      ),
      LocalMediaRecord,
      PrefetchHooks Function()
    >;
typedef $$LocalOutboxTableCreateCompanionBuilder =
    LocalOutboxCompanion Function({
      Value<int> id,
      required String operation,
      Value<String> entityType,
      Value<String> entityRef,
      Value<String> payload,
      Value<String> status,
      Value<int> retryCount,
      Value<String?> nextRetryAt,
      Value<String?> lastError,
      Value<String> createdAt,
      Value<String?> lastAttemptAt,
    });
typedef $$LocalOutboxTableUpdateCompanionBuilder =
    LocalOutboxCompanion Function({
      Value<int> id,
      Value<String> operation,
      Value<String> entityType,
      Value<String> entityRef,
      Value<String> payload,
      Value<String> status,
      Value<int> retryCount,
      Value<String?> nextRetryAt,
      Value<String?> lastError,
      Value<String> createdAt,
      Value<String?> lastAttemptAt,
    });

class $$LocalOutboxTableFilterComposer
    extends Composer<_$LocalNovaDb, $LocalOutboxTable> {
  $$LocalOutboxTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get operation => $composableBuilder(
    column: $table.operation,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get entityType => $composableBuilder(
    column: $table.entityType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get entityRef => $composableBuilder(
    column: $table.entityRef,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get retryCount => $composableBuilder(
    column: $table.retryCount,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastError => $composableBuilder(
    column: $table.lastError,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastAttemptAt => $composableBuilder(
    column: $table.lastAttemptAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalOutboxTableOrderingComposer
    extends Composer<_$LocalNovaDb, $LocalOutboxTable> {
  $$LocalOutboxTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get operation => $composableBuilder(
    column: $table.operation,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get entityType => $composableBuilder(
    column: $table.entityType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get entityRef => $composableBuilder(
    column: $table.entityRef,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get retryCount => $composableBuilder(
    column: $table.retryCount,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastError => $composableBuilder(
    column: $table.lastError,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastAttemptAt => $composableBuilder(
    column: $table.lastAttemptAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalOutboxTableAnnotationComposer
    extends Composer<_$LocalNovaDb, $LocalOutboxTable> {
  $$LocalOutboxTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get operation =>
      $composableBuilder(column: $table.operation, builder: (column) => column);

  GeneratedColumn<String> get entityType => $composableBuilder(
    column: $table.entityType,
    builder: (column) => column,
  );

  GeneratedColumn<String> get entityRef =>
      $composableBuilder(column: $table.entityRef, builder: (column) => column);

  GeneratedColumn<String> get payload =>
      $composableBuilder(column: $table.payload, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<int> get retryCount => $composableBuilder(
    column: $table.retryCount,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => column,
  );

  GeneratedColumn<String> get lastError =>
      $composableBuilder(column: $table.lastError, builder: (column) => column);

  GeneratedColumn<String> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<String> get lastAttemptAt => $composableBuilder(
    column: $table.lastAttemptAt,
    builder: (column) => column,
  );
}

class $$LocalOutboxTableTableManager
    extends
        RootTableManager<
          _$LocalNovaDb,
          $LocalOutboxTable,
          OutboxItem,
          $$LocalOutboxTableFilterComposer,
          $$LocalOutboxTableOrderingComposer,
          $$LocalOutboxTableAnnotationComposer,
          $$LocalOutboxTableCreateCompanionBuilder,
          $$LocalOutboxTableUpdateCompanionBuilder,
          (
            OutboxItem,
            BaseReferences<_$LocalNovaDb, $LocalOutboxTable, OutboxItem>,
          ),
          OutboxItem,
          PrefetchHooks Function()
        > {
  $$LocalOutboxTableTableManager(_$LocalNovaDb db, $LocalOutboxTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalOutboxTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalOutboxTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalOutboxTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> operation = const Value.absent(),
                Value<String> entityType = const Value.absent(),
                Value<String> entityRef = const Value.absent(),
                Value<String> payload = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int> retryCount = const Value.absent(),
                Value<String?> nextRetryAt = const Value.absent(),
                Value<String?> lastError = const Value.absent(),
                Value<String> createdAt = const Value.absent(),
                Value<String?> lastAttemptAt = const Value.absent(),
              }) => LocalOutboxCompanion(
                id: id,
                operation: operation,
                entityType: entityType,
                entityRef: entityRef,
                payload: payload,
                status: status,
                retryCount: retryCount,
                nextRetryAt: nextRetryAt,
                lastError: lastError,
                createdAt: createdAt,
                lastAttemptAt: lastAttemptAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String operation,
                Value<String> entityType = const Value.absent(),
                Value<String> entityRef = const Value.absent(),
                Value<String> payload = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int> retryCount = const Value.absent(),
                Value<String?> nextRetryAt = const Value.absent(),
                Value<String?> lastError = const Value.absent(),
                Value<String> createdAt = const Value.absent(),
                Value<String?> lastAttemptAt = const Value.absent(),
              }) => LocalOutboxCompanion.insert(
                id: id,
                operation: operation,
                entityType: entityType,
                entityRef: entityRef,
                payload: payload,
                status: status,
                retryCount: retryCount,
                nextRetryAt: nextRetryAt,
                lastError: lastError,
                createdAt: createdAt,
                lastAttemptAt: lastAttemptAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalOutboxTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalNovaDb,
      $LocalOutboxTable,
      OutboxItem,
      $$LocalOutboxTableFilterComposer,
      $$LocalOutboxTableOrderingComposer,
      $$LocalOutboxTableAnnotationComposer,
      $$LocalOutboxTableCreateCompanionBuilder,
      $$LocalOutboxTableUpdateCompanionBuilder,
      (
        OutboxItem,
        BaseReferences<_$LocalNovaDb, $LocalOutboxTable, OutboxItem>,
      ),
      OutboxItem,
      PrefetchHooks Function()
    >;
typedef $$LocalSyncStateTableCreateCompanionBuilder =
    LocalSyncStateCompanion Function({
      Value<int> id,
      required String stateKey,
      Value<String> stateValue,
      Value<String> updatedAt,
    });
typedef $$LocalSyncStateTableUpdateCompanionBuilder =
    LocalSyncStateCompanion Function({
      Value<int> id,
      Value<String> stateKey,
      Value<String> stateValue,
      Value<String> updatedAt,
    });

class $$LocalSyncStateTableFilterComposer
    extends Composer<_$LocalNovaDb, $LocalSyncStateTable> {
  $$LocalSyncStateTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get stateKey => $composableBuilder(
    column: $table.stateKey,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get stateValue => $composableBuilder(
    column: $table.stateValue,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalSyncStateTableOrderingComposer
    extends Composer<_$LocalNovaDb, $LocalSyncStateTable> {
  $$LocalSyncStateTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get stateKey => $composableBuilder(
    column: $table.stateKey,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get stateValue => $composableBuilder(
    column: $table.stateValue,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalSyncStateTableAnnotationComposer
    extends Composer<_$LocalNovaDb, $LocalSyncStateTable> {
  $$LocalSyncStateTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get stateKey =>
      $composableBuilder(column: $table.stateKey, builder: (column) => column);

  GeneratedColumn<String> get stateValue => $composableBuilder(
    column: $table.stateValue,
    builder: (column) => column,
  );

  GeneratedColumn<String> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$LocalSyncStateTableTableManager
    extends
        RootTableManager<
          _$LocalNovaDb,
          $LocalSyncStateTable,
          SyncState,
          $$LocalSyncStateTableFilterComposer,
          $$LocalSyncStateTableOrderingComposer,
          $$LocalSyncStateTableAnnotationComposer,
          $$LocalSyncStateTableCreateCompanionBuilder,
          $$LocalSyncStateTableUpdateCompanionBuilder,
          (
            SyncState,
            BaseReferences<_$LocalNovaDb, $LocalSyncStateTable, SyncState>,
          ),
          SyncState,
          PrefetchHooks Function()
        > {
  $$LocalSyncStateTableTableManager(
    _$LocalNovaDb db,
    $LocalSyncStateTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalSyncStateTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalSyncStateTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalSyncStateTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> stateKey = const Value.absent(),
                Value<String> stateValue = const Value.absent(),
                Value<String> updatedAt = const Value.absent(),
              }) => LocalSyncStateCompanion(
                id: id,
                stateKey: stateKey,
                stateValue: stateValue,
                updatedAt: updatedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String stateKey,
                Value<String> stateValue = const Value.absent(),
                Value<String> updatedAt = const Value.absent(),
              }) => LocalSyncStateCompanion.insert(
                id: id,
                stateKey: stateKey,
                stateValue: stateValue,
                updatedAt: updatedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalSyncStateTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalNovaDb,
      $LocalSyncStateTable,
      SyncState,
      $$LocalSyncStateTableFilterComposer,
      $$LocalSyncStateTableOrderingComposer,
      $$LocalSyncStateTableAnnotationComposer,
      $$LocalSyncStateTableCreateCompanionBuilder,
      $$LocalSyncStateTableUpdateCompanionBuilder,
      (
        SyncState,
        BaseReferences<_$LocalNovaDb, $LocalSyncStateTable, SyncState>,
      ),
      SyncState,
      PrefetchHooks Function()
    >;

class $LocalNovaDbManager {
  final _$LocalNovaDb _db;
  $LocalNovaDbManager(this._db);
  $$LocalChatsTableTableManager get localChats =>
      $$LocalChatsTableTableManager(_db, _db.localChats);
  $$LocalMessagesTableTableManager get localMessages =>
      $$LocalMessagesTableTableManager(_db, _db.localMessages);
  $$LocalUsersTableTableManager get localUsers =>
      $$LocalUsersTableTableManager(_db, _db.localUsers);
  $$LocalMediaTableTableManager get localMedia =>
      $$LocalMediaTableTableManager(_db, _db.localMedia);
  $$LocalOutboxTableTableManager get localOutbox =>
      $$LocalOutboxTableTableManager(_db, _db.localOutbox);
  $$LocalSyncStateTableTableManager get localSyncState =>
      $$LocalSyncStateTableTableManager(_db, _db.localSyncState);
}
