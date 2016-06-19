import socket,json,traceback,os,threading,sys,time
if os.geteuid() == 0:
	print('Do not ever run me as root!')
	sys.exit(1)
PATH = os.path.dirname(os.path.abspath(__file__))+'/'
with open(PATH+'settings.json') as f:
	config = json.load(f)

def makeUnicode(s):
	try:
		return s.decode('utf-8')
	except:
		return s

class SocketConnection(threading.Thread):
	sockfile = ''
	connected = False
	s = False
	stopnow = False
	sendQueue = []
	def __init__(self,sockfile):
		threading.Thread.__init__(self)
		self.sockfile = sockfile
	def send(self,obj):
		if not self.connected:
			self.sendQueue.append(obj)
			return
		try:
			self.s.sendall(bytes(json.dumps(obj)+'\n','utf-8'))
		except:
			if not self.stopnow:
				self.sendQueue.append(obj)
				self.disconnect()
				self.connect()
	def connect(self):
		print('Connecting to socket '+self.sockfile+'...')
		while not self.stopnow: # python doesn't optimize tail recursion, we don't want that!
			try:
				self.s = socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
				self.s.settimeout(5)
				self.s.connect(self.sockfile)
				self.connected = True
				for s in self.sendQueue:
					self.send(s)
				self.sendQueue = []
				return
			except socket.error as e:
				print('Failed, retrying in five seconds')
				time.sleep(5)
	def disconnect(self):
		try:
			self.s.close()
		except:
			pass
		self.connected = False
	def quit(self):
		self.stopnow = True
	def run(self):
		self.connect()
		readbuffer = ''
		while not self.stopnow:
			try:
				readbuffer = makeUnicode(self.s.recv(1024))
			except socket.timeout:
				pass
			except socket.error as e:
				print(e)
				self.disconnect()
				self.connect()
			temp = readbuffer.split('\n')
			readbuffer = temp.pop()
			for line in temp:
				try:
					data = json.loads(line)
					if data['type'] == 'done':
						# TODO: copy&stuff
						self.send({
							'type':'destroy',
							'key':data['key']
						})
				except:
					traceback.print_exc()
		self.disconnect()
if __name__ == '__main__':
	sock = SocketConnection(PATH+config['backend']+'/socket.sock')
	sock.start()
	sock.send({
		'type':'build',
		'build':{
			'type':'github',
			'key':'asdf',
			'github':{
				'username':'Sorunome',
				'repo':'blockdude-gamebuino',
				'branch':'master'
			},
			'path':'blockdude',
			'makefile':{
				'ino':'blockdude.ino',
				'name':'BLOKDUDE'
			},
			'include':['../BLOKDUDE.INF']
		}
	})
	try:
		while True:
			time.sleep(30)
	except KeyboardInterrupt:
		sock.stopnow = True
		try:
			sock.join()
		except:
			pass
