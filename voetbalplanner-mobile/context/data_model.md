## Collections (3)
- users: email (String), display_name (String), photo_url (ImagePath), uid (String), created_time (DateTime), phone_number (String)
- teamChats: text (String), senderId (String), senderName (String), teamId (String), createdAt (DateTime)
  - Used by: TeamChatPage
- directMessages: text (String), senderId (String), senderName (String), receiverId (String), createdAt (DateTime)
  - Used by: DirectChatPage

## Data Structs (8)
- FootMatch: id (String), opponent (String), location (String), matchDatetime (String), arrivalTime (String), isHome (Boolean), status (String), scoreHome (Integer), scoreAway (Integer), teamName (String), coachName (String), fruitHeroName (String), notes (String)
- LineupPlayer: id (String), memberName (String), position (String), jerseyNumber (String), isStarter (Boolean), isCaptain (Boolean)
- MatchGoal: id (String), minute (Integer), type (String), scorerName (String), assistName (String)
- BarDuty: id (String), date (String), shift (String), status (String), teamName (String), members (String), notes (String)
- ClubRef: id (String), name (String)
- UserRef: id (String), name (String), email (String), roles (List<String>), club (DataStruct<ClubRef>)
- LoginData: token (String), user (DataStruct<UserRef>)
- LoginResponse: success (Boolean), data (DataStruct<LoginData>), message (String)

