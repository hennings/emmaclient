using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace LiveResults.Client
{
    public delegate void NHResultDelegate(NHResult newResult);

    public class NHResult : Result
    {
        public int RelayRestarts { get; set; }
        public int RelayTeamId { get; set; }
        public int RelayLeg { get; set; }
        public int RelayLegTime { get; set; }
        public double Timestamp { get; set; }
        public List<NHResultStruct> SplitTimes { get; set; }

    }

}
