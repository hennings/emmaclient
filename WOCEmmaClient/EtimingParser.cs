using System;
using System.Collections.Generic;
using System.Text;
using System.Data.OleDb;
using System.Data;
using System.Globalization;

namespace LiveResults.Client
{
    public class EtimingParser : IExternalSystemResultParser
    {
//        private IDbConnection m_Connection;
        private OleDbConnection m_Connection;
        private int m_EventRaceId;

        public event ResultDelegate OnResult;
        public event LogMessageDelegate OnLogMessage;

        private bool m_Continue = false;
        public EtimingParser(OleDbConnection conn, int eventRaceId)
        {
            m_Connection = conn;
            m_EventRaceId = eventRaceId;
        }

        private void FireOnResult(Result newResult)
        {
            if (OnResult != null)
            {
                OnResult(newResult);
            }
        }
        private void FireLogMsg(string msg)
        {
            if (OnLogMessage != null)
                OnLogMessage(msg);
        }

        System.Threading.Thread th;

        public void Start()
        {
            m_Continue = true;
            th = new System.Threading.Thread(new System.Threading.ThreadStart(run));
            th.Start();
        }

        public void Stop()
        {
            m_Continue = false;
        }

        private void run()
        {
            OleDbCommand cmd = new OleDbCommand();

            cmd.Connection = m_Connection;

            while (m_Continue)
            {
                try
                {
                    if (m_Connection.State != System.Data.ConnectionState.Open)
                    {
                  
                            m_Connection.Open();
                        }

                    string paramOper = "?";

                    /*Detect eventtype*/
                    bool isRelay = IsThisEventRelay(cmd);

                    string baseCommand;
                    string splitbaseCommand;

                    if (isRelay)
                    {
                        baseCommand = "SELECT n.timechanged, n.id, n.ename as lastname,r.teamno,n.races as restart,n.name as firstname, " +
                            " n.lisens,n.startno,c.class as classname, n.status,n.times, n.place, n.info as shootresult, " +
                            " n.rank as relayteamno, n.seed, n.starttime, n.totaltime, n.pnr, n.intime, " +
                            "(intime-starttime) as mtime,  t.name as teamname , " +
                            "(select sum(intime-starttime) from name n2 where n.rank=n2.rank and n2.seed<n.seed) as relaytime " +
                            "FROM Name n, Class c, Team t, Relay r  " +
                            "WHERE n.class=c.code and t.code=r.lgteam AND (n.timechanged>@date or n.intime>@date or @date=0)  AND n.rank=r.lgstartno   " +
                            "ORDER BY intime ASC, startno";

                        splitbaseCommand = "SELECT m.timechanged, n.id, n.ename as lastname,n.races as restart,r.teamno, " +
                            " n.name as firstname , n.lisens,n.startno,c.class as classname,n.status,m.strtid, " +
                            " n.rank as relayteamno, n.seed,n.starttime, m.iplace, m.stasjon, m.mintime, " +
                            " (m.mintime-starttime) as mtime, mtime as mtime2, t.name as teamname, " +
                            " (select sum(intime-starttime) from name n2 where n.rank=n2.rank and n2.seed<n.seed) as relaytime " +
                            "FROM Name n, Class c, Team t, Mellom m, Relay r " +
                            "WHERE n.id=m.id and n.class=c.code and t.code=r.lgteam AND mchanged>? AND iplace<999 AND n.rank=r.lgstartno   " +
                            "ORDER BY mintime ASC, startno";

                    }
                    else
                    {
                        baseCommand = "SELECT timechanged, n.id, n.ename as lastname,n.name as firstname , n.lisens,n.startno,c.class as classname, n.ecard,n.status,n.times, n.place, n.info as shootresult, n.rank, n.seed, n.starttime, n.totaltime, n.pnr, n.intime, (intime-starttime) as mtime,  t.name as teamname  " +
                                "FROM Name n, Class c, Team t  " +
                                "WHERE n.class=c.code and t.code=n.team AND timechanged>?  " +
                                "ORDER BY (intime-starttime) ASC, startno";

                        splitbaseCommand = "SELECT m.timechanged, n.id, n.ename as lastname,n.name as firstname , n.lisens,n.startno,c.class as classname, n.ecard,n.status,m.strtid,n.rank, n.seed,n.starttime, m.iplace, m.stasjon, m.mintime, (m.mintime-starttime) as mtime, t.name as teamname " +
                            "FROM Name n, Class c, Team t, Mellom m " +
                            "WHERE n.id=m.id and n.class=c.code and t.code=n.team AND mchanged>? " +
                            "ORDER BY (mintime-starttime) ASC, startno";
                    }


                    cmd.CommandText = baseCommand; 
                    IDbDataParameter param = cmd.CreateParameter();
                    param.ParameterName = "date";
                    param.Value = 0.0;
                    param.DbType = DbType.Double;

                    IDbCommand cmdSplits = m_Connection.CreateCommand();
                    cmdSplits.CommandText = splitbaseCommand;
                    IDbDataParameter paramSplit = cmd.CreateParameter();
                    paramSplit.ParameterName = "date";
                    paramSplit.Value = 0.0;
                    paramSplit.DbType = DbType.Double;

                       
/*                    IDbDataParameter splitparam = cmdSplits.CreateParameter();
                    splitparam.ParameterName = "date";
                        splitparam.Value = DateTime.Now;
  */                 
    
                    cmd.Parameters.Add(param);

                    cmdSplits.Parameters.Add(paramSplit);


                    double lastDateTime = 0.0;
                    double lastSplitDateTime = 0.0;
                    FireLogMsg("Etiming Monitor thread started");
                    IDataReader reader = null;
                    while (m_Continue)
                    {
                        string lastRunner = "";
                        try
                        {
                            /*Kontrollera om nya klasser*/
                            /*Kontrollera om nya resultat*/
                                (cmd.Parameters["date"] as IDbDataParameter).Value = lastDateTime;
                               // (cmdSplits.Parameters["date"] as IDbDataParameter).Value = lastSplitDateTime;
                            

                            string command = cmd.CommandText;
                            cmd.Prepare();
                            reader = cmd.ExecuteReader();
                            while (reader.Read())
                            {
                                Double modDate = 0.0, time = 0.0;
                                int  runnerID = 0, iStartTime = 0, iTime = 0;
                                string famName = "", fName = "", club = "", classN = "", status = "";
                                Double relayTeamTime = 0.0;

                                try
                                {
                                    if (reader[0] != null && reader[0] != DBNull.Value)
                                    {
                                        modDate = Convert.ToDouble(reader[0]);
                                        if (modDate > lastDateTime) lastDateTime = modDate;
                                    }

                                    runnerID = Convert.ToInt32(reader["id"].ToString());

                                    time = -9;
                                    if (reader["mtime"] != null && reader["mtime"] != DBNull.Value) //  mtime = intime - starttime   - directly in the SQL
                                        time = Convert.ToDouble(reader["mtime"].ToString());

                                    famName = (reader["lastname"] as string);
                                    fName = (reader["firstname"] as string);

                                    club = (reader["teamname"] as string);
                                    classN = (reader["classname"] as string);
                                    status = reader["status"] as string;

                                    double startTime = 0.0;

                                    if (reader["starttime"] != null && reader["starttime"] != DBNull.Value)
                                    {
                                        startTime = Convert.ToDouble(reader["starttime"]);
                                    }

                                    if (isRelay)
                                    {
                                        if (reader["teamno"] != null && reader["teamno"] != DBNull.Value)
                                        {
                                            int teamno = Convert.ToInt16(reader["teamno"]);
                                            if (teamno > 1) club = club + " " + teamno;
                                        }

                                        classN = classN + "-" + Convert.ToString(reader["seed"]);

                                        if (reader["relaytime"] != DBNull.Value)
                                        {
                                            relayTeamTime = Convert.ToDouble(reader["relaytime"].ToString());
                                            //                                            FireLogMsg("Relay time: " + relayTeamTime);
                                        }
                                        //relayLegTime = Convert.ToInt32(((time % 1) * 60 * 60 * 24 + 0.001) * 100);
                                        time = relayTeamTime + time;


                                        /*if (reader["relayteamno"] != null && reader["relayteamno"] != DBNull.Value)
                                        {
                                            extra2 = ""+Convert.ToInt16(reader["relayteamno"]);
                                        }

                                        if (reader["restart"] != null && reader["restart"] != DBNull.Value)
                                        {
                                            extra1 = "" + Convert.ToInt16(reader["restart"]);
                                        }
                                        */
                                        
                                        /*
                                        if (reader["intime"] != DBNull.Value)
                                        {
                                            double ft = Convert.ToDouble(reader["intime"].ToString());
                                        }
                                         * 
                                         * How to handle leg 4...?
                                         */
                                    }
                                    iStartTime = Convert.ToInt32( ((startTime % 1) * 60 * 60 * 24 + 0.1) * 100); // iStartTime = seconds*100

                                }
                                catch (Exception ee)
                                {
                                    FireLogMsg(ee.Message);
                                }

                                iTime = Convert.ToInt32 (((time % 1) * 60 * 60 * 24 + 0.1) * 100); // iTime = seconds*100
                                

                                /*
                                    time is seconds * 100
                                 * 
                                 * valid status is
                                    notStarted
                                    finishedTimeOk
                                    finishedPunchOk
                                    disqualified
                                    finished
                                    movedUp
                                    walkOver
                                    started
                                    passed
                                    notValid
                                    notActivated
                                    notParticipating
                                 */
                                //EMMAClient.RunnerStatus rstatus = EMMAClient.RunnerStatus.Passed;
                                int rstatus = 999;
                                switch (status)
                                {
                                    case "S": // Started
                                        rstatus = 9;
                                        break;
                                    case "I": // Entered
                                        rstatus = 10;
                                        //rstatus = EMMAClient.RunnerStatus.NotStartedYet;
                                        break;
                                    case "N": // Did not start
                                        rstatus = 1;
                                        //rstatus = EMMAClient.RunnerStatus.NotStarted;
                                        break;
                                    case "D": // DSQ
                                        rstatus = 4;
                                        break;
                                    case "B": // DNF
                                        rstatus = 3;
                                        //rstatus = EMMAClient.RunnerStatus.MissingPunch;
                                        break;
                                    case "A":  // OK!
                                        rstatus = 0;
                                        break;
                                }
                                if (rstatus != 999)
                                    FireOnResult(
                                        new Result()
                                        {
                                            ID = runnerID,
                                            RunnerName = fName + " " + famName,
                                            RunnerClub = club,
                                            Class = classN,
                                            StartTime = iStartTime,
                                            Time = iTime,
                                            Status = rstatus
                                        });
                            }
                            reader.Close();


                            (cmdSplits.Parameters["date"] as IDbDataParameter).Value = lastSplitDateTime;
                            reader = cmdSplits.ExecuteReader();
                            while (reader.Read())
                            {
                                try
                                {
                                    
                             // last modified time

                                    double modDate = Convert.ToDouble(reader[0]);
                                    lastSplitDateTime = (modDate > lastSplitDateTime ? modDate : lastSplitDateTime);

                                    double startTime = 0.0, mInTime = 0.0 , mTime = 0.0;
                                    int iStartTime = 0, iMTime = 0;

                                    if (reader["starttime"] != null && reader["starttime"] != DBNull.Value)
                                    {
                                        startTime = Convert.ToDouble(reader["starttime"]);
                                    }

                                    iStartTime = Convert.ToInt32( ((startTime % 1) * 60 * 60 * 24 + 0.1) * 100); // iStartTime = seconds*100

                                    if (reader["mintime"] != null && reader["mintime"] != DBNull.Value)
                                    {
                                        mInTime = Convert.ToDouble(reader["mintime"]);
                                    }
                                    if (reader["mtime"] != null && reader["mtime"] != DBNull.Value)
                                    {
                                        mTime = Convert.ToDouble(reader["mtime"]);
                                        iMTime = Convert.ToInt32( ((mTime % 1) * 60 * 60 * 24 + 0.1) * 100); //  seconds*100
                                    }
                                    int code = Convert.ToInt32(reader["iplace"]);                                    

                                    List<ResultStruct> times = new List<ResultStruct>();
                                    ResultStruct t = new ResultStruct();
                                    t.ControlCode = 1 * 1000 + code;  // 1 =) passingcount
                                    t.ControlNo = 0;
                                    t.Time = iMTime;
                                    times.Add(t);

                                    
                                       int  runnerID = 0;
                                string famName = "", fName = "", club = "", classN = "", status = "", extra1="", extra2="";
                             
                                    runnerID = Convert.ToInt32(reader["id"].ToString());

                                    famName = (reader["lastname"] as string);
                                    fName = (reader["firstname"] as string);

                                    club = (reader["teamname"] as string);
                                    classN = (reader["classname"] as string);

                                    if (isRelay)
                                    {
                                        classN = classN + "-" + Convert.ToString(reader["seed"]);

                                        extra1 = reader["restart"] as string;
                                        extra2 = reader["relayteamno"] as string;

                                        if (reader["teamno"] != null && reader["teamno"] != DBNull.Value)
                                        {
                                            int teamno = Convert.ToInt16(reader["teamno"]);
                                            if (teamno > 1) club = club + " " + teamno;
                                        }
                                    }

                                    FireOnResult(new Result()
                                    {
                                        ID = runnerID,
                                        RunnerName = fName + " " + famName,
                                        RunnerClub = club,
                                        Class = classN,
                                        StartTime = 0,
                                        Time = -2,
                                        Status = 0,
                                        SplitTimes = times
                                    });


                                }
                                catch (Exception ee)
                                {
                                    FireLogMsg(ee.Message);
                                }
                            }
                            reader.Close();

                            System.Threading.Thread.Sleep(1000);
                        }
                        catch (Exception ee)
                        {
                            if (reader != null)
                                reader.Close();
                            FireLogMsg("Etiming Parser: " + ee.Message + " {parsing: " + lastRunner);

                            System.Threading.Thread.Sleep(100);

                            switch (m_Connection.State)
                            {
                                case ConnectionState.Broken:
                                case ConnectionState.Closed:
                                    m_Connection.Close();
                                    m_Connection.Open();
                                    break;
                            }
                        }
                    }
                }
                catch (Exception ee)
                {
                    FireLogMsg("Etiming Parser: " +ee.Message);
                }
                finally
                {
                    if (m_Connection != null)
                    {
                        m_Connection.Close();
                    }
                    FireLogMsg("Disconnected");
                    FireLogMsg("Etiming Monitor thread stopped");

                }
            }
        }

        private bool IsThisEventRelay(OleDbCommand cmd)
        {
            bool isRelay = false;
            string eventTypeSql = "SELECT kid FROM arr";
            cmd.CommandText = eventTypeSql;
            cmd.Prepare();
            IDataReader readeret = cmd.ExecuteReader();

            while (readeret.Read())
            {
                if (readeret[0] != null && readeret[0] != DBNull.Value)
                {
                    int kid = Convert.ToInt16(readeret[0]);
                    FireLogMsg("Event type is " + kid);
                    if (kid == 3)
                    {
                        isRelay = true;
                    }
                }
            }
            readeret.Close();

            return isRelay;
        }

        private static DateTime ParseDateTime(string tTime)
        {
            DateTime startTime;
            if (!DateTime.TryParseExact(tTime, "yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture, DateTimeStyles.None, out startTime))
            {
                if (!DateTime.TryParseExact(tTime, "yyyy-MM-dd HH:mm:ss.f", CultureInfo.InvariantCulture, DateTimeStyles.None, out startTime))
                {
                    if (!DateTime.TryParseExact(tTime, "yyyy-MM-dd HH:mm:ss.ff", CultureInfo.InvariantCulture, DateTimeStyles.None, out startTime))
                    {
                        if (!DateTime.TryParseExact(tTime, "yyyy-MM-dd HH:mm:ss.fff", CultureInfo.InvariantCulture, DateTimeStyles.None, out startTime))
                        {
                        }
                    }
                }
            }
            return startTime;
        }
    }
}
