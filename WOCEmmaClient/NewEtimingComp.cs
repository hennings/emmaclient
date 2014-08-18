using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Text;
using System.Windows.Forms;
using System.IO;
using System.Threading;
using System.Xml;
using System.Text.RegularExpressions;
using System.Xml.Serialization;
using System.Data.OleDb;

namespace LiveResults.Client
{
    public partial class NewEtimingComp : Form
    {
        List<EmmaMysqlClient> m_Clients;
        string SETTINGS_XML = "etiming-settings.xml" ;

        StreamWriter w ; 

        EtimingParser pars;

        public NewEtimingComp()
        {
            InitializeComponent();
            Text = Text += ", " + Encoding.Default.EncodingName + "," + Encoding.Default.CodePage;
 
            string path = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "EmmaClient");
            m_Clients = new List<EmmaMysqlClient>();

            w = File.AppendText(Path.Combine(path, "log-emmaclient-hs.txt"));

            w.WriteLine("Startup.");

            string file = Path.Combine(path, SETTINGS_XML);
            

            if (File.Exists(file))
            {
                try
                {
                    var fs = File.OpenRead(file);
                    XmlSerializer ser = new XmlSerializer(typeof(Settings));
                    Settings s = ser.Deserialize(fs) as Settings;
                    fs.Close();
                    if (s != null)
                    {
                        txtEtimingMdb.Text = s.Location;
                        txtSystemMdb.Text = s.SystemMdb;
                        txtCompID.Text = s.CompID.ToString();

                    }
                }
                catch
                {
                }
            }

        }


        private void button1_Click(object sender, EventArgs e)
        {
            if (fileBrowserDialog1.ShowDialog(this) == DialogResult.OK)
            {
                txtEtimingMdb.Text = fileBrowserDialog1.FileName;
            }
        }

        private void button2_Click(object sender, EventArgs e)
        {
            if (!File.Exists(txtEtimingMdb.Text))
            {
                MessageBox.Show(this, "Please select an existing etime.mdb", "Start eTime Monitor", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }
            if (!File.Exists(txtSystemMdb.Text))
            {
                MessageBox.Show(this, "Please select an existing system.mdb", "Start eTime Monitor", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            if (string.IsNullOrEmpty(txtCompID.Text))
            {
                MessageBox.Show(this, "You must enter a competition-ID", "Start eTime Monitor", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            listBox1.Items.Clear();
            m_Clients.Clear();
            logit("Reading servers from config (eventually resolving online)");
            Application.DoEvents();
            EmmaMysqlClient.EmmaServer[] servers = EmmaMysqlClient.GetServersFromConfig();
            logit("Got servers from obasen...");
            Application.DoEvents();
            foreach (EmmaMysqlClient.EmmaServer server in servers)
            {
                EmmaMysqlClient client = new EmmaMysqlClient(server.host, 3306, server.user, server.pw, server.db, Convert.ToInt32(txtCompID.Text));

                client.OnLogMessage += new LogMessageDelegate(client_OnLogMessage);
                client.Start();
                m_Clients.Add(client);
            }

            string dsn = "Provider=Microsoft.Jet.OLEDB.4.0;Data Source="+txtEtimingMdb.Text+";Persist Security Info=False";
            logit("DSN; " + dsn);
            OleDbConnection m_Connection = new OleDbConnection(dsn);

            pars = new EtimingParser(m_Connection, Convert.ToInt32(txtCompID.Text));

            pars.OnLogMessage += 
                delegate(string msg)
            {
                logit(msg);
            };

            pars.OnResult += new ResultDelegate(m_Parser_OnResult);
            logit("ready to run... starting EtimingParser");
            pars.Start();
          
        }

        void m_Parser_OnResult(Result newResult)
        {
            foreach (EmmaMysqlClient client in m_Clients)
            {
                if (!client.IsRunnerAdded(newResult.ID))
                    client.AddRunner(new Runner(newResult.ID, newResult.RunnerName, newResult.RunnerClub, newResult.Class));
                else
                    client.UpdateRunnerInfo(newResult.ID, newResult.RunnerName, newResult.RunnerClub, 
                        newResult.Class); //, newResult.RelayRestarts, newResult.RelayTeamId, newResult.RelayLeg, newResult.RelayLegTime, newResult.Timestamp);

                if (newResult.StartTime > 0)
                    client.SetRunnerStartTime(newResult.ID, newResult.StartTime);


                if (newResult.Time != -2)
                {
                          client.SetRunnerResult(newResult.ID, newResult.Time, newResult.Status);
                }

                if (newResult.SplitTimes != null)
                {
                    foreach (ResultStruct str in newResult.SplitTimes)
                    {
                         client.SetRunnerSplit(newResult.ID, str.ControlCode, str.Time);
                    }
                }
            }
        }

        void client_OnLogMessage(string msg)
        {
            logit(msg);
        }


        void logit(string msg)
        {
            if (!listBox1.IsDisposed)
            {
                listBox1.Invoke(new MethodInvoker(delegate
                {
                    listBox1.Items.Insert(0, DateTime.Now.ToString("HH:mm:ss") + " " + msg);
                }));
            }
                w.WriteLine(DateTime.Now.ToString("HH:mm:ss") + " " + msg);


        }

        private void button3_Click(object sender, EventArgs e)
        {
            if (m_Clients != null)
            {
                foreach (EmmaMysqlClient c in m_Clients)
                {
                    c.Stop();
                }
                m_Clients.Clear();
                
            }
            if (pars != null)
            {
                logit("Stopping EtimingParser");
                pars.Stop();
            }
        }

        private void NewEtimingComp_Closing(object sender, FormClosingEventArgs e)
        {
            try
            {
                Settings s = new Settings()
                {
                    Location = txtEtimingMdb.Text,
                    SystemMdb = txtSystemMdb.Text,
                    CompID = int.Parse(txtCompID.Text),
                };

                string path = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "EmmaClient");
                if (!Directory.Exists(path))
                    Directory.CreateDirectory(path);

                string file = Path.Combine(path, SETTINGS_XML);
                var fs = File.Create(file);
                XmlSerializer ser = new XmlSerializer(typeof(Settings));
                ser.Serialize(fs, s);
                fs.Close();
            }
            catch
            {
            }
        }

        [Serializable]
        public class Settings
        {
            public string Location { get; set; }
            public string SystemMdb { get; set; }
            public int CompID { get; set; }
     
        }


        private void button4_Click(object sender, EventArgs e)
        {
            if (fileBrowserDialog2.ShowDialog(this) == DialogResult.OK)
            {
                txtSystemMdb.Text = fileBrowserDialog2.FileName;
                logit("SystemMdb = " + txtSystemMdb.Text);
            }
        }
    }
}