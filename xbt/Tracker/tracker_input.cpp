#include "stdafx.h"
#include "tracker_input.h"

void Ctracker_input::set(const std::string& name, const std::string& value)
{
	if (name.empty())
            return;

    switch (name[0])
    {
    	case 'c':
			if (name == "compact")
				m_compact = atoi(value.c_str());
			break;
			
	    case 'd':
            if (name == "downloaded")
                    m_downloaded = to_int(value);
            break;
	    case 'e':
            if (name == "event")
            {
                if (value == "completed")
                    m_event = e_completed;
                else if (value == "started")
                    m_event = e_started;
                else if (value == "stopped")
                    m_event = e_stopped;
                else
                    m_event = e_none;
            }
            break;
	    case 'i':
            if (name == "info_hash" && value.size() == 20)
            {
                m_info_hash = value;
                m_info_hashes.push_back(value);
            }
            else if (name == "ip")
                m_ipa = inet_addr(value.c_str());
            break;
	    case 'l':
            if (name == "left")
                m_left = to_int(value);
            break;
        case 'n':
			if (name == "numwant")
				m_num_want = atoi(value.c_str());
			break;
	    case 'p':
            if (name == "peer_id" && value.size() == 20) {
                memcpy(m_peer_id, value);
                //this->peer_id2a();
            }
            else if (name == "port")
                m_port = htons(to_int(value));
            break;
	    case 'u':
            if (name == "uploaded")
                m_uploaded = to_int(value);
            break;
    }
}

bool Ctracker_input::valid() const
{
	return m_downloaded >= 0
		&& (m_event != e_completed || !m_left)
		&& m_info_hash.size() == 20
		&& m_left >= -1
		&& m_peer_id.size() == 20
		&& m_port >= 0
		&& m_uploaded >= 0
        && m_compact == 1;
}

void Ctracker_input::peer_id2a()
{
    if (m_peer_id.size() != 20 || m_peer_id[7] != '-')
        return;

    std::string str = std::string(&m_peer_id[1], 2);
    for (auto j : agents) 
    {
        if (j.first == str) {
            m_agent = (boost::format("%s (%s)") % j.second % std::string(&m_peer_id[3], 4)).str();
            break;
        }
    }
}

bool Ctracker_input::banned() const
{
	if (m_agent[0] == '*')
		return true;

	return false;
}

std::map<std::string, std::string> Ctracker_input::agents = {
    {"AG","Ares"},
    {"A~","Ares"},
    {"AR","Arctic"},
    {"AV","Avicora"},
    {"AX","BitPump"},
    {"AZ","Azureus"},
    {"BB","BitBuddy"},
    {"BC","BitComet"},
    {"BF","Bitflu"},
    {"BG","BTG"},
    {"BR","BitRocket"},
    {"BS","BTSlave"},
    {"BX","~Bittorrent X"},
    {"CD","Enhanced CTorrent"},
    {"CT","CTorrent"},
    {"DE","DelugeTorrent"},
    {"DP","Propagate Data Client"},
    {"EB","EBit"},
    {"ES","electric sheep"},
    {"FT","FoxTorrent"},
    {"GS","GSTorrent"},
    {"HL","Halite"},
    {"HN","Hydranode"},
    {"KG","KGet"},
    {"KT","KTorrent"},
    {"LH","LH-ABC"},
    {"LP","Lphant"},
    {"LT","libtorrent"},
    {"lt","libTorrent"},
    {"LW","LimeWire"},
    {"MO","MonoTorrent"},
    {"MP","MooPolice"},
    {"MR","Miro"},
    {"MT","MoonlightTorrent"},
    {"NX","Net Transport"},
    {"PD","Pando"},
    {"qB","qBittorrent"},
    {"QD","QQDownload"},
    {"QT","Qt 4"},
    {"RT","Retriever"},
    {"S~","Shareaza"},
    {"SB","~Swiftbit"},
    {"SS","SwarmScope"},
    {"ST","SymTorrent"},
    {"st","sharktorrent"},
    {"SZ","Shareaza"},
    {"TN","TorrentDotNET"},
    {"TR","Transmission"},
    {"TS","Torrentstorm"},
    {"TT","TuoTu"},
    {"UL","uLeecher!"},
    {"UT","µTorrent"},
    {"VG","Vagaa"},
    {"WT","BitLet"},
    {"WY","FireTorrent"},
    {"XL","Xunlei"},
    {"XT","XanTorrent"},
    {"XX","Xtorrent"},
    {"ZT","ZipTorrent"}
};