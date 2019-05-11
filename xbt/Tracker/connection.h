#pragma once

#include "client.h"

class Cconnection : public Cclient, boost::noncopyable
{
public:
	int run();
	void read(const std::string&);
	int recv();
	int send();
	virtual void process_events(int);
	int pre_select(fd_set* read, fd_set* write);
	int post_select(fd_set* read, fd_set* write);
	Cconnection(const Csocket&, const sockaddr_in&);
private:
	std::string get_header(const std::string, const std::string&); // @Aayush

	sockaddr_in m_a;
	time_t m_ctime;
	int m_state;
	std::array<char, 4 << 10> m_read_b;
	shared_data m_write_b;
	str_ref m_r;
	mutable_str_ref m_w;
	std::string m_agent; // @Aayush
	std::string m_ipa; // @Aayush
	const char* m_p;
};

typedef const boost::iterator_range<std::string::const_iterator> StringRange;
