## API Calls
### Group: VoetbalPlannerAPI (https://voetbalplanner.nubix.nl/api/v1)
- Login [POST] /auth/login
  - Variables: email (String), password (String)
  - Body: JSON
  - Response: DataStruct<LoginResponse>
- GetMe [GET] /auth/me
  - Variables: token (String)
- Logout [POST] /auth/logout
  - Variables: token (String)
- GetMatches [GET] /matches?per_page=15&page=[page]
  - Variables: token (String), page (Integer)
  - Response: List<List<DataStruct<?>>>
- GetMatch [GET] /matches/[id]
  - Variables: token (String), id (String)
  - Response: DataStruct<FootMatch>
- GetLineup [GET] /matches/[id]/lineup
  - Variables: token (String), id (String)
  - Response: List<List<DataStruct<?>>>
- GetGoals [GET] /matches/[id]/goals
  - Variables: token (String), id (String)
  - Response: List<List<DataStruct<?>>>
- GetDriveSchedule [GET] /matches?is_home=false&has_drivers=1&per_page=50
  - Variables: token (String)
  - Response: List<List<DataStruct<?>>>
- GetBarDuties [GET] /bar-duties?per_page=15&page=[page]
  - Variables: token (String), page (Integer)
  - Response: List<List<DataStruct<?>>>

