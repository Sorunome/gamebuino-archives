import os,sys,lxc,subprocess,getpass,shutil,traceback,time,shlex,threading,json,re,ntpath

PATH = os.path.dirname(os.path.abspath(__file__))+'/'
USERNAME = getpass.getuser()
PID = os.getpid()
with open(PATH+'settings.json') as f:
	config = json.load(f)

def setCgroup():
	for p in os.listdir('/sys/fs/cgroup/'):
		with open('/sys/fs/cgroup/'+p+'/'+USERNAME+'/tasks','w+') as f:
			f.write(str(PID))

def copy_file(a,b):
	with open(a,'rb') as f:
		with open(b,'wb+') as g:
			g.write(f.read())
box_num = 0
class Box:
	STATE_UNREADY = 0
	STATE_BUILDING = 1
	STATE_READY = 2
	STATE_RUNNING = 3
	STATE_DONE = 4
	
	TYPE_NOBOX = -1
	TYPE_BUILD = 0
	TYPE_EXAMIN = 1
	
	def __init__(self):
		global box_num
		self.i = box_num
		box_num += 1
		self.sandbox_path = ''
		self.state = self.STATE_UNREADY
		self.boxtype = self.TYPE_NOBOX
		self.commands = []
		self.key = ''
		self.thread = None
		self.success = False
		
	@classmethod
	def rmTemplate(self):
		c = lxc.Container('gamebuino-buildserver-template')
		if c.defined:
			if c.state == 'RUNNING' and not c.shutdown(timeout=30):
				c.stop()
			c.destroy()
	@classmethod
	def makeTemplate(self):
		try:
			print('Sandbox template absent, building...')
			if os.path.isfile(PATH+'/mktemplate.lock'):
				print('another thread is building right now...')
				while os.path.isfile(PATH+'/mktemplate.lock'):
					time.sleep(1)
				c = lxc.Container('gamebuino-buildserver-template')
				return c.defined
			open(PATH+'/mktemplate.lock','a').close()
			self.rmTemplate()
			
			subprocess.call(['lxc-create','-n','gamebuino-buildserver-template','-f',PATH+'lxc.conf','-t','download','--','-d','debian','-r','jessie','-a','amd64'])
			c = lxc.Container('gamebuino-buildserver-template')
			assert(c.defined)
			c.start()
			assert(c.running)
			
			
			c.attach_wait(lxc.attach_run_command,['touch','/prepbox.sh'],env_policy=lxc.LXC_ATTACH_CLEAR_ENV)
			c.attach_wait(lxc.attach_run_command,['chmod','777','/prepbox.sh'],env_policy=lxc.LXC_ATTACH_CLEAR_ENV)
			
			# we need to copy this file without removing it else we don't have perms
			copy_file(PATH+'prepbox.sh',c.get_config_item('lxc.rootfs')+'/prepbox.sh')
			
			c.attach_wait(lxc.attach_run_command,['/prepbox.sh'],env_policy=lxc.LXC_ATTACH_CLEAR_ENV)
			
			for f in os.listdir(PATH+'makefiles'):
				fp = PATH+'makefiles/'+f
				if os.path.isfile(fp):
					copy_file(fp,c.get_config_item('lxc.rootfs')+'/makefiles/'+f)
			
			if c.defined:
				if c.running and not c.shutdown(timeout=30):
					c.stop()
			os.remove(PATH+'/mktemplate.lock')
			return True
		except:
			#self.rmTemplate()
			traceback.print_exc()
			os.remove(PATH+'/mktemplate.lock')
			if c.defined:
				if c.running and not c.shutdown(timeout=30):
					c.stop()
				c.destroy()
			return False
	def setBoxType(self,boxtype):
		self.boxtype = boxtype
	def build_box(self):
		print('Building sandbox '+str(self.i)+' ...')
		try:
			self.success = False
			if self.state == self.STATE_READY:
				return True
			assert(self.state == self.STATE_UNREADY)
			
			self.state = self.STATE_BUILDING
			ct = lxc.Container('gamebuino-buildserver-template')
			
			if os.path.isfile(PATH+'/sandbox/mktemplate.lock') or not ct.defined:
				assert(self.makeTemplate())
			
			if ct.running and not ct.shutdown(timeout=30):
				ct.stop()
			assert(not ct.running)
			
			self.destroy_box(False)
			
			subprocess.call(['lxc-copy','-n','gamebuino-buildserver-template','-N','gamebuino-buildserver-'+str(self.i)])
			c = lxc.Container('gamebuino-buildserver-'+str(self.i))
			
			assert(c.defined)
			
			self.state = self.STATE_READY
			self.commands = []
			
			self.thread = threading.Thread(target=self.run)
			return True
		except Exception as inst:
			print(inst)
			traceback.print_exc()
			time.sleep(10) # we don't want a busy wait!
			self.destroy_box()
			return False
	def destroy_box(self,triggerBuild = True):
		print('Destroying box '+str(self.i))
		c = lxc.Container('gamebuino-buildserver-'+str(self.i))
		if c.defined:
			if c.state == 'RUNNING' and not c.shutdown(timeout=30):
				c.stop()
			c.destroy()
		
		self.state = self.STATE_UNREADY
		self.boxtype = self.TYPE_NOBOX
		if triggerBuild:
			print('Building box '+str(self.i)+' in new thread')
			threading.Thread(target=self.build_box).start()
	def set_key(self,key):
		self.key = key
	def build_path(self,path):
		self.commands += [
			{
				'type':'exec',
				'fn':lambda:os.chdir(path)
			}
		]
	def build_movepath(self,movepath):
		self.commands += [
			{
				'type':'cmd',
				'cmd':['mkdir','-p',movepath]
			},
			{
				'type':'cmd',
				'cmd':['rsync','-a','.',movepath+'/','--exclude','.*','--exclude','Arduino','--exclude','bin']
			},
			{
				'type':'exec',
				'fn':lambda:os.chdir(movepath)
			}
		]
	def build_includefiles(self,files):
		for f in files:
			if isinstance(f,str):
				self.commands += [
					{
						'type':'cmd',
						'cmd':['cp',f,'/build/bin/'+ntpath.basename(f)]
					}
				]
			else:
				self.commands += [
					{
						'type':'cmd',
						'cmd':['cp',f[0],'/build/bin/'+f[1]]
					}
				]
	def build_command(self,cmd):
		self.commands += [
			{
				'type':'cmd',
				'cmd':'timeout '+str(config['timeout'])+' '+cmd
			}
		]
	def build_makefile(self,makefile):
		self.commands += [
			{
				'type':'cmd',
				'cmd':['cp','/makefiles/'+makefile,'Makefile']
			}
		]
	def build_zip(self,path):
		c = lxc.Container('gamebuino-buildserver-'+str(self.i))
		
		copy_file(path,c.get_config_item('lxc.rootfs')+'/source.zip')
		
		self.commands += [
			{
				'type':'cmd',
				'cmd':['unzip','/source.zip','-d','/build']
			}
		]
	def build_git(self,repo):
		self.commands += [
			{
				'type':'cmd',
				'cmd':['mkdir','repo']
			},
			{
				'type':'cmd',
				'cmd':['git','clone','--depth','1',repo,'repo']
			},
			{
				'type':'cmd',
				'cmd':'mv repo/* .'
			}
		]
	def examin(self):
		print('examining this thing....')
		inofile = ''
		inodirectory = ''
		name = ''
		include = []
		movepath = ''
		
		# we try to find the ino file by <name>/<name>.ino first
		for root, dirs, files in os.walk('.'):
			dirs[:] = [d for d in dirs if d not in ['Arduino','.arduino15','bin']]
			for f in files:
				if f[-4:] == '.ino':
					print('Found INO file',f)
					fname = f[:-4]
					if root[-len(fname):] == fname:
						inofile = f
						inodirectory = root
			if inofile != '':
				break
		
		# well, if we didn't find anything....let's start looking into the files themself!
		if inofile == '':
			for root, dirs, files in os.walk('.'):
				dirs[:] = [d for d in dirs if d not in ['Arduino','.arduino15','bin']]
				for f in files:
					if f[-4:] == '.ino':
						print('Found INO file',f)
						with open(root+'/'+f) as o:
							c = o.read()
							if re.search(r"void\s+setup\([^)]*\)\s*{",c) and re.search(r"void\s+loop\([^)]*\)\s*{",c):
								print('Found you sucker!')
								movepath = f[:-4]
								inofile = f
								inodirectory = root
								break
				if inofile != '':
					break
		
		#now search for the name and additional INF files to include!
		if inofile != '':
			for root, dirs, files in os.walk('.'):
				dirs[:] = [d for d in dirs if d not in ['Arduino','.arduino15','bin']]
				for f in files:
					ext = f[-4:]
					if ext == '.HEX' or ext == '.INF':
						name = f[:-4][:8].upper()
					if ext == '.INF':
						path = "../"*(len(os.path.split(inodirectory+'/'))-1)+root+'/'+f
						
						include.append(os.path.normpath(path))
		
		if name == '':
			name = inofile[:-4][:8].upper()
		inodirectory = os.path.normpath(inodirectory)
		
		with open('/build/.resp_code','w+') as f:
			f.write(str(int(inofile == '')))
		
		json_obj = {
			'ino_file':inofile,
			'path':inodirectory,
			'filename':name,
			'include':include,
			'movepath':movepath
		}
		with open('/build/.results.json','w+') as f:
			f.write(json.dumps(json_obj))
	def build_examin(self):
		self.commands += [
			{
				'type':'exec',
				'fn':self.examin
			}
		]
	def demote(self):
		try:
			print('Running lxc container ('+str(self.i)+')...')
			
			if self.state != self.STATE_RUNNING:
				return
			
			cmd_before = [
				'dhclient eth0',
				'iptables -F',
				'iptables -P INPUT DROP',
				'iptables -P FORWARD DROP',
				'iptables -P OUTPUT DROP',
				'iptables -A INPUT -p icmp --icmp-type echo-reply -j ACCEPT',
				'iptables -A OUTPUT -p icmp --icmp-type echo-request -j ACCEPT',
				'iptables -A INPUT -p udp -i eth0 --sport domain -j ACCEPT',
				'iptables -A OUTPUT -p udp -o eth0 --dport domain -j ACCEPT',
				'iptables -A INPUT -p tcp --dport 80 -m limit --limit 25/minute --limit-burst 100 -j ACCEPT'
			]
			for p in ['http','https','ssh','ftp','domain',9418]:
				cmd_before.append('iptables -A INPUT -i eth0 -p tcp --sport '+str(p)+' -m state --state ESTABLISHED -j ACCEPT')
				cmd_before.append('iptables -A OUTPUT -o eth0 -p tcp --dport '+str(p)+' -m state --state NEW,ESTABLISHED -j ACCEPT')
			for c in cmd_before:
				print('(box '+str(self.i)+') Running setup command '+c)
				subprocess.call(c,shell=True)
			
			os.chdir('/build')
			
			
			os.setgid(1000) # set GID before UID so that we actually have permission
			os.setuid(1000)
			
			with open('/build/.output','w+') as o:
				for c in self.commands:
					print('(box '+str(self.i)+') Executing command '+str(c))
					o.write('Executing command '+str(c)+':\n')
					o.flush()
					if c['type'] == 'cmd':
						if isinstance(c['cmd'],list):
							c['cmd'] = ' '.join(shlex.quote(s) for s in c['cmd'])
						resp = subprocess.call(c['cmd'],shell=True,stdout=o,stderr=subprocess.STDOUT)
						if 'noerror' in c and c['noerror']:
							resp = 0
						with open('/build/.resp_code','w+') as f:
							f.write(str(resp))
						if resp != 0:
							break
					elif c['type'] == 'exec':
						c['fn']()
					o.write('\n\n\n')
					o.flush()
			
		except:
			traceback.print_exc()
			with open('/build/.resp_code','w+') as e:
				e.write('1')
	def doneQueue(self):
		return True
	def run(self):
		if self.state != self.STATE_READY:
			self.doneQueue()
			return
		self.state = self.STATE_RUNNING
		outfile = PATH+'output/'+self.key
		try:
			os.lstat(outfile)
			os.remove(outfile)
		except:
			pass
		try:
			c = lxc.Container('gamebuino-buildserver-'+str(self.i))
			
			os.symlink(c.get_config_item('lxc.rootfs')+'/build/.output',outfile)
			
			assert(c.defined)
			c.start()
			assert(c.running)
			
			
			c.attach_wait(self.demote,env_policy=lxc.LXC_ATTACH_CLEAR_ENV)
			
			self.success = False
			try:
				with open(c.get_config_item('lxc.rootfs')+'/build/.resp_code','r') as f:
					self.success = f.read() == '0'
			except:
				traceback.print_exc()
				self.success = False
		except:
			self.success = False
		
		self.state = self.STATE_DONE
		
		if self.doneQueue():
			self.destroy_box()
			print('Done with box_'+str(self.i))

setCgroup()